using Dapper;
using Npgsql;

namespace StatsService.Api.Features.EventStats;

public interface IEventSalesReader
{
    Task<EventSalesData> ReadAsync(long eventId, CancellationToken cancellationToken);
}

public sealed record EventSalesData(SalesTotalsRow Totals, long TicketsOnHold, IReadOnlyList<DailySalesRow> Daily);

public sealed class SalesTotalsRow
{
    public long Orders { get; init; }
    public long TicketsSold { get; init; }
    public decimal Revenue { get; init; }
    public long Customers { get; init; }
}

public sealed class DailySalesRow
{
    public DateTime Day { get; init; }
    public long Tickets { get; init; }
    public decimal Revenue { get; init; }
}

public sealed class EventSalesReader(NpgsqlDataSource dataSource) : IEventSalesReader
{
    // Las tres consultas viajan en un solo round-trip; usan el índice (event_id, status) de ticket_sales
    private const string Sql = """
        SELECT COUNT(*)                        AS orders,
               COALESCE(SUM(quantity), 0)      AS tickets_sold,
               COALESCE(SUM(total_amount), 0)  AS revenue,
               COUNT(DISTINCT user_id)         AS customers
        FROM ticket_sales
        WHERE event_id = @EventId AND status = 'completed';

        SELECT COALESCE(SUM(quantity), 0)
        FROM reservations
        WHERE event_id = @EventId AND status = 'pending' AND expires_at > now();

        SELECT (purchase_date AT TIME ZONE 'UTC')::date AS day,
               SUM(quantity)                           AS tickets,
               SUM(total_amount)                       AS revenue
        FROM ticket_sales
        WHERE event_id = @EventId AND status = 'completed'
        GROUP BY day
        ORDER BY day;
        """;

    public async Task<EventSalesData> ReadAsync(long eventId, CancellationToken cancellationToken)
    {
        await using var connection = await dataSource.OpenConnectionAsync(cancellationToken);
        var command = new CommandDefinition(Sql, new { EventId = eventId }, cancellationToken: cancellationToken);

        await using var results = await connection.QueryMultipleAsync(command);

        var totals = await results.ReadSingleAsync<SalesTotalsRow>();
        var onHold = await results.ReadSingleAsync<long>();
        var daily = (await results.ReadAsync<DailySalesRow>()).ToList();

        return new EventSalesData(totals, onHold, daily);
    }
}
