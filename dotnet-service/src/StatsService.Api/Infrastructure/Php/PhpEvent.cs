namespace StatsService.Api.Infrastructure.Php;

public sealed record PhpEvent(
    long Id,
    string Name,
    string Description,
    DateTimeOffset EventDate,
    decimal TicketPrice,
    int TotalTickets,
    int AvailableTickets,
    string Status);

public sealed record PhpEventPage(
    IReadOnlyList<PhpEvent> Items,
    int Page,
    int PerPage,
    int Total);
