using StatsService.Api.Infrastructure.Php;

namespace StatsService.Api.Features.Events;

public static class GetEventEndpoint
{
    public static void Map(IEndpointRouteBuilder api) => api
        .MapGet("/events/{id:long}", HandleAsync)
        .WithTags("Events")
        .WithSummary("Gets an event detail, fetched from the PHP service.")
        .ProducesProblem(StatusCodes.Status404NotFound)
        .ProducesProblem(StatusCodes.Status503ServiceUnavailable);

    private static async Task<EventResponse> HandleAsync(long id, IEventsClient eventsClient, CancellationToken cancellationToken)
    {
        return EventResponse.From(await eventsClient.GetEventAsync(id, cancellationToken));
    }
}
