namespace StatsService.Api.Features.SalesSummary;

public sealed class GetSalesSummaryHandler(ISalesSummaryReader reader, TimeProvider clock)
{
    public async Task<SalesSummaryResponse> HandleAsync(int topEvents, CancellationToken cancellationToken)
    {
        var data = await reader.ReadAsync(topEvents, cancellationToken);
        var totals = data.Totals;

        return new SalesSummaryResponse(
            new SalesTotals(
                totals.Orders,
                totals.TicketsSold,
                totals.Revenue,
                totals.Customers,
                totals.Orders == 0 ? 0 : Math.Round(totals.Revenue / totals.Orders, 2)),
            data.ByStatus.Select(s => new EventsByStatus(s.Status, s.Events, s.Capacity)).ToList(),
            data.TopEvents.Select(e => new TopEvent(e.EventId, e.Name, e.TicketsSold, e.Revenue, e.OccupancyPercent)).ToList(),
            clock.GetUtcNow());
    }
}
