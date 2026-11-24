namespace StatsService.Api.Features.EventStats;

public sealed record EventStatsResponse(
    EventInfo Event,
    TicketStats Tickets,
    SalesStats Sales,
    IReadOnlyList<DailySales> DailySales,
    int DaysUntilEvent);

public sealed record EventInfo(long Id, string Name, DateTimeOffset EventDate, string Status);

public sealed record TicketStats(int Total, long Sold, long OnHold, int Available, decimal OccupancyPercent);

public sealed record SalesStats(long Orders, long TicketsSold, decimal Revenue, long Customers, decimal AverageOrderValue);

public sealed record DailySales(DateOnly Date, long Tickets, decimal Revenue);
