using StatsService.Api.Infrastructure.Php;

namespace StatsService.Api.Features.Events;

public sealed record EventResponse(
    long Id,
    string Name,
    string Description,
    DateTimeOffset EventDate,
    decimal TicketPrice,
    int TotalTickets,
    int AvailableTickets,
    string Status)
{
    public static EventResponse From(PhpEvent e) => new(
        e.Id, e.Name, e.Description, e.EventDate, e.TicketPrice, e.TotalTickets, e.AvailableTickets, e.Status);
}

public sealed record EventListResponse(IReadOnlyList<EventResponse> Items, int Page, int PerPage, int Total);
