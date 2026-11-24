using Microsoft.AspNetCore.Http.HttpResults;
using Microsoft.AspNetCore.Mvc;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Api.Features.Events;

public static class ListEventsEndpoint
{
    public static void Map(IEndpointRouteBuilder api) => api
        .MapGet("/events", HandleAsync)
        .WithTags("Events")
        .WithSummary("Lists published events, fetched from the PHP service.")
        .ProducesProblem(StatusCodes.Status503ServiceUnavailable);

    private static async Task<Results<Ok<EventListResponse>, ValidationProblem>> HandleAsync(
        IEventsClient eventsClient,
        string? search,
        int page = 1,
        [FromQuery(Name = "per_page")] int perPage = 15,
        CancellationToken cancellationToken = default)
    {
        var errors = new Dictionary<string, string[]>();

        if (page < 1)
        {
            errors["page"] = ["Must be greater than or equal to 1."];
        }

        if (perPage is < 1 or > 50)
        {
            errors["per_page"] = ["Must be between 1 and 50."];
        }

        if (errors.Count > 0)
        {
            return TypedResults.ValidationProblem(errors);
        }

        var result = await eventsClient.ListEventsAsync(search, page, perPage, cancellationToken);

        return TypedResults.Ok(new EventListResponse(
            result.Items.Select(EventResponse.From).ToList(), result.Page, result.PerPage, result.Total));
    }
}
