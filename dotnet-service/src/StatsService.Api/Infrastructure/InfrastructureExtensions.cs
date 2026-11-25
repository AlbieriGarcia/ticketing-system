using Dapper;
using Microsoft.Extensions.Caching.Hybrid;
using Microsoft.Extensions.Options;
using Microsoft.OpenApi;
using Npgsql;
using StatsService.Api.Infrastructure.Errors;
using StatsService.Api.Infrastructure.Messaging;
using StatsService.Api.Infrastructure.Php;
using StatsService.Api.Infrastructure.Security;

namespace StatsService.Api.Infrastructure;

public static class InfrastructureExtensions
{
    public static IServiceCollection AddInfrastructure(this IServiceCollection services, IConfiguration configuration)
    {
        DefaultTypeMap.MatchNamesWithUnderscores = true;

        services.AddProblemDetails();
        services.AddExceptionHandler<GlobalExceptionHandler>();
        services.AddSingleton(TimeProvider.System);
        services.Configure<ApiKeyOptions>(configuration.GetSection(ApiKeyOptions.SectionName));

        services.AddSingleton(_ => NpgsqlDataSource.Create(
            configuration.GetConnectionString("Stats")
            ?? throw new InvalidOperationException("Connection string 'Stats' is not configured.")));

        services.AddOptions<PhpApiOptions>()
            .Bind(configuration.GetSection(PhpApiOptions.SectionName))
            .ValidateDataAnnotations()
            .ValidateOnStart();

        services
            .AddHttpClient<PhpEventsClient>((sp, client) =>
            {
                var options = sp.GetRequiredService<IOptions<PhpApiOptions>>().Value;
                client.BaseAddress = new Uri(options.BaseUrl.TrimEnd('/') + "/");
                client.DefaultRequestHeaders.Accept.ParseAdd("application/json");
            })
            .AddStandardResilienceHandler(o =>
            {
                // Reintentos con backoff, timeout por intento y circuit breaker si PHP falla repetidamente
                o.AttemptTimeout.Timeout = TimeSpan.FromSeconds(2);
                o.TotalRequestTimeout.Timeout = TimeSpan.FromSeconds(6);
                o.Retry.MaxRetryAttempts = 2;
            });

        // Quien pide IEventsClient recibe la réplica con PHP por HTTP como respaldo
        services.AddSingleton<EventReplica>();
        services.AddTransient<IEventsClient>(sp => new ReplicatedEventsClient(
            sp.GetRequiredService<PhpEventsClient>(),
            sp.GetRequiredService<EventReplica>()));

        services.AddHybridCache(o => o.DefaultEntryOptions = new HybridCacheEntryOptions
        {
            // Los mensajes invalidan al instante; el TTL solo acota lo viejo si el consumidor estuviera desconectado
            Expiration = TimeSpan.FromMinutes(5),
            LocalCacheExpiration = TimeSpan.FromMinutes(5),
        });

        services.AddMessaging(configuration);

        return services;
    }

    private static void AddMessaging(this IServiceCollection services, IConfiguration configuration)
    {
        var section = configuration.GetSection(RabbitMqOptions.SectionName);

        if (!section.GetValue(nameof(RabbitMqOptions.Enabled), defaultValue: true))
        {
            return;
        }

        services.AddOptions<RabbitMqOptions>()
            .Bind(section)
            .ValidateDataAnnotations()
            .ValidateOnStart();

        services.AddHostedService<RabbitMqConsumer>();
    }

    public static IServiceCollection AddSwaggerDocs(this IServiceCollection services)
    {
        services.AddEndpointsApiExplorer();
        services.AddSwaggerGen(o =>
        {
            o.SwaggerDoc("v1", new OpenApiInfo
            {
                Title = "Stats API",
                Version = "1.0.0",
                Description = "Statistics and reporting service. Integrates with the PHP Ticketing API.",
            });

            o.AddSecurityDefinition("ApiKey", new OpenApiSecurityScheme
            {
                Type = SecuritySchemeType.ApiKey,
                In = ParameterLocation.Header,
                Name = ApiKeyOptions.HeaderName,
                Description = "Service-to-service API key",
            });

            o.AddSecurityRequirement(document => new OpenApiSecurityRequirement
            {
                [new OpenApiSecuritySchemeReference("ApiKey", document)] = [],
            });
        });

        return services;
    }
}
