using System.Collections.Concurrent;

namespace StatsService.Api.Infrastructure.Php;

// Copia local de los eventos de PHP, alimentada por los mensajes de RabbitMQ
public sealed class EventReplica(TimeProvider clock)
{
    // Si se perdiera un mensaje, la copia no queda vieja para siempre: tras este tiempo se vuelve a pedir a PHP
    public static readonly TimeSpan MaxAge = TimeSpan.FromMinutes(10);

    private readonly ConcurrentDictionary<long, Entry> _events = new();

    public bool TryGet(long id, out PhpEvent phpEvent)
    {
        if (_events.TryGetValue(id, out var entry) && clock.GetUtcNow() - entry.StoredAt < MaxAge)
        {
            phpEvent = entry.Event;
            return true;
        }

        phpEvent = null!;
        return false;
    }

    // Aplica un mensaje solo si es más nuevo que lo guardado: descarta duplicados y mensajes fuera de orden
    public bool Apply(PhpEvent phpEvent, long sequence)
    {
        var candidate = new Entry(phpEvent, sequence, clock.GetUtcNow());
        var stored = _events.AddOrUpdate(
            phpEvent.Id,
            candidate,
            (_, existing) => sequence > existing.Sequence ? candidate : existing);

        return ReferenceEquals(stored, candidate);
    }

    // Guarda lo obtenido por HTTP sin pisar una copia vigente, que pudo llegar por mensaje durante la petición
    public void StoreFetched(PhpEvent phpEvent)
    {
        var now = clock.GetUtcNow();

        _events.AddOrUpdate(
            phpEvent.Id,
            new Entry(phpEvent, Sequence: 0, now),
            (_, existing) => now - existing.StoredAt < MaxAge ? existing : new Entry(phpEvent, existing.Sequence, now));
    }

    private sealed record Entry(PhpEvent Event, long Sequence, DateTimeOffset StoredAt);
}
