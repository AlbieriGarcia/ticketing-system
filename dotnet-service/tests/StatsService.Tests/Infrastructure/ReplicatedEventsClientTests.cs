using StatsService.Api.Infrastructure.Errors;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Tests.Infrastructure;

public class ReplicatedEventsClientTests
{
    private readonly FakeEventsClient _php = new();
    private readonly EventReplica _replica = new(new ManualTimeProvider(TestData.Now));
    private readonly ReplicatedEventsClient _client;

    public ReplicatedEventsClientTests()
    {
        _client = new ReplicatedEventsClient(_php, _replica);
    }

    [Fact]
    public async Task Replica_hit_does_not_call_php()
    {
        _replica.Apply(TestData.Event(available: 42), sequence: 1);
        _php.IsDown = true;

        var phpEvent = await _client.GetEventAsync(16, CancellationToken.None);

        Assert.Equal(42, phpEvent.AvailableTickets);
        Assert.Equal(0, _php.GetEventCalls);
    }

    [Fact]
    public async Task Miss_falls_back_to_php_and_stores_the_event()
    {
        _php.Events[16] = TestData.Event(available: 30);

        await _client.GetEventAsync(16, CancellationToken.None);
        await _client.GetEventAsync(16, CancellationToken.None);

        Assert.Equal(1, _php.GetEventCalls);
    }

    [Fact]
    public async Task Draft_in_replica_is_not_found()
    {
        _replica.Apply(TestData.Event(status: "draft"), sequence: 1);

        await Assert.ThrowsAsync<EventNotFoundException>(() => _client.GetEventAsync(16, CancellationToken.None));
    }
}
