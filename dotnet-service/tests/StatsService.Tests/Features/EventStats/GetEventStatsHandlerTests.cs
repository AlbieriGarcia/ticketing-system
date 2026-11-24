using StatsService.Api.Features.EventStats;
using StatsService.Api.Infrastructure.Errors;

namespace StatsService.Tests.Features.EventStats;

public class GetEventStatsHandlerTests
{
    private readonly FakeEventsClient _events = new();
    private readonly FakeEventSalesReader _sales = new();
    private readonly GetEventStatsHandler _handler;

    public GetEventStatsHandlerTests()
    {
        _handler = new GetEventStatsHandler(_events, _sales, new FixedTimeProvider(TestData.Now));
    }

    [Fact]
    public async Task Combines_php_event_with_sales_data()
    {
        _events.Events[16] = TestData.Event(total: 500, available: 108);
        _sales.Data = new EventSalesData(
            new SalesTotalsRow { Orders = 190, TicketsSold = 380, Revenue = 11_400_000m, Customers = 150 },
            12,
            [new DailySalesRow { Day = new DateTime(2026, 11, 22), Tickets = 120, Revenue = 3_600_000m }]);

        var stats = await _handler.HandleAsync(16, CancellationToken.None);

        Assert.Equal("Rock Festival", stats.Event.Name);
        Assert.Equal(380, stats.Tickets.Sold);
        Assert.Equal(12, stats.Tickets.OnHold);
        Assert.Equal(108, stats.Tickets.Available);
        Assert.Equal(76.00m, stats.Tickets.OccupancyPercent);
        Assert.Equal(60_000.00m, stats.Sales.AverageOrderValue);
        Assert.Equal(27, stats.DaysUntilEvent);
        Assert.Equal(new DateOnly(2026, 11, 22), Assert.Single(stats.DailySales).Date);
    }

    [Fact]
    public async Task Event_without_sales_has_zero_averages()
    {
        _events.Events[16] = TestData.Event();

        var stats = await _handler.HandleAsync(16, CancellationToken.None);

        Assert.Equal(0, stats.Tickets.OccupancyPercent);
        Assert.Equal(0, stats.Sales.AverageOrderValue);
        Assert.Empty(stats.DailySales);
    }

    [Fact]
    public async Task Past_event_reports_zero_days_until_event()
    {
        _events.Events[16] = TestData.Event(date: new DateTimeOffset(2026, 1, 1, 0, 0, 0, TimeSpan.Zero));

        var stats = await _handler.HandleAsync(16, CancellationToken.None);

        Assert.Equal(0, stats.DaysUntilEvent);
    }

    [Fact]
    public async Task Unknown_event_propagates_not_found()
    {
        await Assert.ThrowsAsync<EventNotFoundException>(() => _handler.HandleAsync(99, CancellationToken.None));
    }
}
