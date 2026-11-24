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

    private static Task<EventStatsResponse> HandleAsync(long id, GetEventStatsHandler handler, CancellationToken cancellationToken)
    {
        return handler.HandleAsync(id, cancellationToken);
    }
}
