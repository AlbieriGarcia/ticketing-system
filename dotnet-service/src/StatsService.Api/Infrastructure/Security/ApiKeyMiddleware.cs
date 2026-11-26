using System.Security.Cryptography;
using System.Text;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Options;

namespace StatsService.Api.Infrastructure.Security;

public sealed class ApiKeyOptions
{
    public const string SectionName = "Security";
    public const string HeaderName = "X-Api-Key";

    public string ApiKey { get; init; } = string.Empty;
}

// El 401 sale como ProblemDetails, igual que el resto de errores, y así también lleva trace_id
public sealed class ApiKeyMiddleware(RequestDelegate next, IOptions<ApiKeyOptions> options, IProblemDetailsService problemDetails)
{
    private readonly byte[] _expectedKey = Encoding.UTF8.GetBytes(options.Value.ApiKey);

    public async Task InvokeAsync(HttpContext context)
    {
        if (!context.Request.Path.StartsWithSegments("/api"))
        {
            await next(context);
            return;
        }

        var provided = context.Request.Headers[ApiKeyOptions.HeaderName].ToString();

        // Comparación en tiempo constante para no filtrar información de la clave por timing
        if (_expectedKey.Length == 0 || !CryptographicOperations.FixedTimeEquals(Encoding.UTF8.GetBytes(provided), _expectedKey))
        {
            context.Response.StatusCode = StatusCodes.Status401Unauthorized;
            await problemDetails.WriteAsync(new ProblemDetailsContext
            {
                HttpContext = context,
                ProblemDetails = new ProblemDetails
                {
                    Status = StatusCodes.Status401Unauthorized,
                    Title = "Invalid or missing API key.",
                },
            });
            return;
        }

        await next(context);
    }
}
