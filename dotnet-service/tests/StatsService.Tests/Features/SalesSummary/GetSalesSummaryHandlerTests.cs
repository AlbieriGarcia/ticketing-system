using StatsService.Api.Features.SalesSummary;

namespace StatsService.Tests.Features.SalesSummary;

public class GetSalesSummaryHandlerTests
{
    [Fact]
    public async Task Maps_totals_status_breakdown_and_top_events()
    {
        var reader = new FakeSalesSummaryReader
        {
            Data = new SummaryData(
                new SummaryTotalsRow { Orders = 4, TicketsSold = 10, Revenue = 1000m, Customers = 3 },
                [new EventsByStatusRow { Status = "published", Events = 2, Capacity = 600 }],
                [new TopEventRow { EventId = 1, Name = "A", TicketsSold = 10, Revenue = 1000m, OccupancyPercent = 5m }]),
        };
        var handler = new GetSalesSummaryHandler(reader, new FixedTimeProvider(TestData.Now));

        var summary = await handler.HandleAsync(5, CancellationToken.None);

        Assert.Equal(250m, summary.Sales.AverageOrderValue);
        Assert.Equal("published", Assert.Single(summary.EventsByStatus).Status);
        Assert.Equal(1, Assert.Single(summary.TopEvents).EventId);
        Assert.Equal(TestData.Now, summary.GeneratedAt);
    }

    [Fact]
    public async Task No_sales_gives_zero_average()
    {
        var handler = new GetSalesSummaryHandler(new FakeSalesSummaryReader(), new FixedTimeProvider(TestData.Now));

        var summary = await handler.HandleAsync(5, CancellationToken.None);

        Assert.Equal(0, summary.Sales.AverageOrderValue);
        Assert.Empty(summary.TopEvents);
    }
}
