using Microsoft.AspNetCore.Http.HttpResults;

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

        return TypedResults.Ok(await handler.HandleAsync(top, cancellationToken));
    }
}
