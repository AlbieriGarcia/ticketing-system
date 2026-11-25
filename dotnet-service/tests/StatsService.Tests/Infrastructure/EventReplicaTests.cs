using StatsService.Api.Infrastructure.Php;

namespace StatsService.Tests.Infrastructure;

public class EventReplicaTests
{
    private readonly ManualTimeProvider _clock = new(TestData.Now);
    private readonly EventReplica _replica;

    public EventReplicaTests()
    {
        _replica = new EventReplica(_clock);
    }

    [Fact]
    public void Newer_message_replaces_and_older_or_duplicate_is_ignored()
    {
        Assert.True(_replica.Apply(TestData.Event(available: 100), sequence: 5));
        Assert.True(_replica.Apply(TestData.Event(available: 90), sequence: 7));

        Assert.False(_replica.Apply(TestData.Event(available: 95), sequence: 6));
        Assert.False(_replica.Apply(TestData.Event(available: 90), sequence: 7));

        Assert.True(_replica.TryGet(16, out var stored));
        Assert.Equal(90, stored.AvailableTickets);
    }

    [Fact]
    public void Fetched_event_does_not_override_fresh_message_data()
    {
        _replica.Apply(TestData.Event(available: 80), sequence: 3);

        _replica.StoreFetched(TestData.Event(available: 120));

        _replica.TryGet(16, out var stored);
        Assert.Equal(80, stored.AvailableTickets);
    }

    [Fact]
    public void Entry_expires_and_refetch_keeps_sequence_to_reject_old_messages()
    {
        _replica.Apply(TestData.Event(available: 80), sequence: 10);
        _clock.Advance(EventReplica.MaxAge);

        Assert.False(_replica.TryGet(16, out _));

        _replica.StoreFetched(TestData.Event(available: 70));

        Assert.False(_replica.Apply(TestData.Event(available: 99), sequence: 9));
        Assert.True(_replica.TryGet(16, out var stored));
        Assert.Equal(70, stored.AvailableTickets);
    }
}
