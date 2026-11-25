using System.Text.Json;
using Microsoft.Extensions.Caching.Hybrid;
using StatsService.Api.Infrastructure.Caching;
using StatsService.Api.Infrastructure.Messaging;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Api.Features.Sync;

// event.changed trae la foto completa del evento: actualiza la réplica e invalida lo que dependía de él
public sealed class EventChangedHandler(EventReplica replica, HybridCache cache, ILogger<EventChangedHandler> logger) : IMessageHandler
{
    public string MessageType => "event.changed";

    public async Task HandleAsync(IntegrationMessage message, CancellationToken cancellationToken)
    {
        var phpEvent = message.Data.Deserialize<PhpEvent>(PhpJson.Options)
                       ?? throw new JsonException("event.changed without data.");

        if (!replica.Apply(phpEvent, message.Sequence))
        {
            logger.LogDebug("Skipping stale event.changed {Sequence} for event {EventId}", message.Sequence, phpEvent.Id);
            return;
        }

        // El resumen agrupa eventos por estado y capacidad, así que también cambia
        await cache.RemoveByTagAsync([StatsCacheTags.Event(phpEvent.Id), StatsCacheTags.Summary], cancellationToken);
    }
}
