using System.Text.Json;
using Microsoft.Extensions.Caching.Hybrid;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging.Abstractions;
using StatsService.Api.Features.Sync;
using StatsService.Api.Infrastructure.Messaging;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Tests.Features.Sync;

public class EventChangedHandlerTests
{
    private readonly EventReplica _replica = new(new ManualTimeProvider(TestData.Now));
    private readonly EventChangedHandler _handler;

    public EventChangedHandlerTests()
    {
        var cache = new ServiceCollection().AddHybridCache().Services.BuildServiceProvider().GetRequiredService<HybridCache>();
        _handler = new EventChangedHandler(_replica, cache, NullLogger<EventChangedHandler>.Instance);
    }

    [Fact]
    public async Task Applies_php_snapshot_and_ignores_older_ones()
    {
        await _handler.HandleAsync(Message(sequence: 8, available: 460), CancellationToken.None);
        await _handler.HandleAsync(Message(sequence: 7, available: 462), CancellationToken.None);

        Assert.True(_replica.TryGet(1, out var stored));
        Assert.Equal(460, stored.AvailableTickets);
        Assert.Equal(45.00m, stored.TicketPrice);
    }

    // Mismo formato que publica el outbox de PHP: snake_case y decimales como string
    private static IntegrationMessage Message(long sequence, int available)
    {
        var data = JsonDocument.Parse($$"""
            {
              "id": 1, "name": "Jazz Night", "description": "Live", "event_date": "2026-12-21T21:00:00+00:00",
              "ticket_price": "45.00", "total_tickets": 500, "available_tickets": {{available}}, "status": "published"
            }
            """).RootElement;

        return new IntegrationMessage(Guid.NewGuid(), "event.changed", sequence, TestData.Now, data);
    }
}
