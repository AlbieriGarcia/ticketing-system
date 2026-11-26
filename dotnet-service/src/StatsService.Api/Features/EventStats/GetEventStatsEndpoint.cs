using Microsoft.Extensions.Caching.Hybrid;
using StatsService.Api.Infrastructure.Caching;

namespace StatsService.Api.Features.EventStats;

public static class GetEventStatsEndpoint
{
    public static IServiceCollection AddEventStats(this IServiceCollection services) => services
        .AddScoped<IEventSalesReader, EventSalesReader>()
        .AddScoped<GetEventStatsHandler>();

    public static void Map(IEndpointRouteBuilder api) => api
        .MapGet("/stats/events/{id:long}", HandleAsync)
        .WithTags("Stats")
        .WithSummary("Sales statistics of an event: occupancy, revenue and daily sales.")
        .ProducesProblem(StatusCodes.Status404NotFound)
        .ProducesProblem(StatusCodes.Status503ServiceUnavailable);

    // Los errores no se cachean: un 404 o un 503 se vuelven a evaluar en la siguiente petición
    private static async Task<EventStatsResponse> HandleAsync(
        long id,
        GetEventStatsHandler handler,
        HybridCache cache,
        CancellationToken cancellationToken)
    {
        return await cache.GetOrCreateTracedAsync(
            $"stats:event:{id}",
            (handler, id),
            static (state, ct) => new ValueTask<EventStatsResponse>(state.handler.HandleAsync(state.id, ct)),
            tags: [StatsCacheTags.Event(id)],
            cancellationToken: cancellationToken);
    }
}
