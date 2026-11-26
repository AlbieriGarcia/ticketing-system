using OpenTelemetry;
using OpenTelemetry.Logs;
using OpenTelemetry.Trace;

namespace StatsService.Api.Infrastructure.Observability;

public static class ObservabilityExtensions
{
    // Endpoint, protocolo y nombre del servicio llegan por las variables estándar OTEL_* (ver docker-compose)
    public static IServiceCollection AddObservability(this IServiceCollection services, IConfiguration configuration)
    {
        // Sin colector configurado (tests, dotnet run sin Seq) no se exporta nada; los logs siguen saliendo por consola
        if (string.IsNullOrWhiteSpace(configuration["OTEL_EXPORTER_OTLP_ENDPOINT"]))
        {
            return services;
        }

        services.AddOpenTelemetry()
            .WithTracing(tracing => tracing
                .AddAspNetCoreInstrumentation()
                .AddHttpClientInstrumentation()
                .AddSource(Telemetry.SourceName))
            .WithLogging()
            .UseOtlpExporter();

        return services;
    }
}
