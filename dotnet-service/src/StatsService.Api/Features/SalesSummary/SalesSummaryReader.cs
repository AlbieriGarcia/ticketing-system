using Dapper;
using Npgsql;

namespace StatsService.Api.Features.SalesSummary;

public interface ISalesSummaryReader
{
    Task<SummaryData> ReadAsync(int topEvents, CancellationToken cancellationToken);
}

public sealed record SummaryData(SummaryTotalsRow Totals, IReadOnlyList<EventsByStatusRow> ByStatus, IReadOnlyList<TopEventRow> TopEvents);

public sealed class SummaryTotalsRow
{
    public long Orders { get; init; }
    public long TicketsSold { get; init; }
    public decimal Revenue { get; init; }
    public long Customers { get; init; }
}

public sealed class EventsByStatusRow
{
    public string Status { get; init; } = string.Empty;
    public long Events { get; init; }
    public long Capacity { get; init; }
}

public sealed class TopEventRow
{
    public long EventId { get; init; }
    public string Name { get; init; } = string.Empty;
    public long TicketsSold { get; init; }
    public decimal Revenue { get; init; }
    public decimal OccupancyPercent { get; init; }
}

public sealed class SalesSummaryReader(NpgsqlDataSource dataSource) : ISalesSummaryReader
{
    private const string Sql = """
        SELECT COUNT(*)                        AS orders,
               COALESCE(SUM(quantity), 0)      AS tickets_sold,
               COALESCE(SUM(total_amount), 0)  AS revenue,
               COUNT(DISTINCT user_id)         AS customers
        FROM ticket_sales
        WHERE status = 'completed';

        SELECT status,
               COUNT(*)                        AS events,
               COALESCE(SUM(total_tickets), 0) AS capacity
        FROM events
        GROUP BY status
        ORDER BY status;

        SELECT e.id                                                  AS event_id,
               e.name,
               SUM(s.quantity)                                       AS tickets_sold,
               SUM(s.total_amount)                                   AS revenue,
               ROUND(100.0 * SUM(s.quantity) / e.total_tickets, 2)   AS occupancy_percent
        FROM ticket_sales s
        JOIN events e ON e.id = s.event_id
        WHERE s.status = 'completed'
        GROUP BY e.id, e.name, e.total_tickets
        ORDER BY revenue DESC
        LIMIT @Top;
        """;

    public async Task<SummaryData> ReadAsync(int topEvents, CancellationToken cancellationToken)
    {
        await using var connection = await dataSource.OpenConnectionAsync(cancellationToken);
        var command = new CommandDefinition(Sql, new { Top = topEvents }, cancellationToken: cancellationToken);

        await using var results = await connection.QueryMultipleAsync(command);

        var totals = await results.ReadSingleAsync<SummaryTotalsRow>();
        var byStatus = (await results.ReadAsync<EventsByStatusRow>()).ToList();
        var top = (await results.ReadAsync<TopEventRow>()).ToList();

        return new SummaryData(totals, byStatus, top);
    }
}
