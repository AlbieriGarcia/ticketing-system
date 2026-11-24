using StatsService.Api.Infrastructure.Php;

namespace StatsService.Api.Features.EventStats;

public sealed class GetEventStatsHandler(IEventsClient eventsClient, IEventSalesReader salesReader, TimeProvider clock)
{
    public async Task<EventStatsResponse> HandleAsync(long eventId, CancellationToken cancellationToken)
    {
        // El evento (PHP) y las ventas (PostgreSQL) son independientes: se consultan en paralelo
        var eventTask = eventsClient.GetEventAsync(eventId, cancellationToken);
        var salesTask = salesReader.ReadAsync(eventId, cancellationToken);
        await Task.WhenAll(eventTask, salesTask);

        var phpEvent = eventTask.Result;
        var sales = salesTask.Result;
        var totals = sales.Totals;

        var daysUntilEvent = (int)Math.Ceiling((phpEvent.EventDate - clock.GetUtcNow()).TotalDays);

        return new EventStatsResponse(
            new EventInfo(phpEvent.Id, phpEvent.Name, phpEvent.EventDate, phpEvent.Status),
            new TicketStats(
                phpEvent.TotalTickets,
                totals.TicketsSold,
                sales.TicketsOnHold,
                phpEvent.AvailableTickets,
                phpEvent.TotalTickets == 0 ? 0 : Math.Round(100m * totals.TicketsSold / phpEvent.TotalTickets, 2)),
            new SalesStats(
                totals.Orders,
                totals.TicketsSold,
                totals.Revenue,
                totals.Customers,
                totals.Orders == 0 ? 0 : Math.Round(totals.Revenue / totals.Orders, 2)),
            sales.Daily.Select(d => new DailySales(DateOnly.FromDateTime(d.Day), d.Tickets, d.Revenue)).ToList(),
            Math.Max(0, daysUntilEvent));
    }
}
