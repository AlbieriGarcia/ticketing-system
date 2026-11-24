namespace StatsService.Api.Features.SalesSummary;

public sealed record SalesSummaryResponse(
    SalesTotals Sales,
    IReadOnlyList<EventsByStatus> EventsByStatus,
    IReadOnlyList<TopEvent> TopEvents,
    DateTimeOffset GeneratedAt);

public sealed record SalesTotals(long Orders, long TicketsSold, decimal Revenue, long Customers, decimal AverageOrderValue);

public sealed record EventsByStatus(string Status, long Events, long Capacity);

public sealed record TopEvent(long EventId, string Name, long TicketsSold, decimal Revenue, decimal OccupancyPercent);
