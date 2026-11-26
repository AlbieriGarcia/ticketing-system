using Microsoft.AspNetCore.Http.HttpResults;
using Microsoft.Extensions.Caching.Hybrid;
using StatsService.Api.Infrastructure.Caching;

namespace StatsService.Api.Features.SalesSummary;

public static class GetSalesSummaryEndpoint
{
    public static IServiceCollection AddSalesSummary(this IServiceCollection services) => services
        .AddScoped<ISalesSummaryReader, SalesSummaryReader>()
        .AddScoped<GetSalesSummaryHandler>();

    public static void Map(IEndpointRouteBuilder api) => api
        .MapGet("/stats/summary", HandleAsync)
        .WithTags("Stats")
        .WithSummary("Global sales summary: totals, events by status and top events by revenue.");

    private static async Task<Results<Ok<SalesSummaryResponse>, ValidationProblem>> HandleAsync(
        GetSalesSummaryHandler handler,
        HybridCache cache,
        int top = 5,
        CancellationToken cancellationToken = default)
    {
        if (top is < 1 or > 20)
        {
            return TypedResults.ValidationProblem(new Dictionary<string, string[]>
            {
                ["top"] = ["Must be between 1 and 20."],
            });
        }

        var summary = await cache.GetOrCreateTracedAsync(
            $"stats:summary:{top}",
            (handler, top),
            static (state, ct) => new ValueTask<SalesSummaryResponse>(state.handler.HandleAsync(state.top, ct)),
            tags: [StatsCacheTags.Summary],
            cancellationToken: cancellationToken);

        return TypedResults.Ok(summary);
    }
}
