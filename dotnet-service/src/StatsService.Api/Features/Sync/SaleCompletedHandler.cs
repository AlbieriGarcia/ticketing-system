using System.Text.Json;
using Microsoft.Extensions.Caching.Hybrid;
using StatsService.Api.Infrastructure.Caching;
using StatsService.Api.Infrastructure.Messaging;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Api.Features.Sync;

// Una venta nueva cambia los totales del evento y del resumen; las cifras se recalculan en la próxima consulta
public sealed class SaleCompletedHandler(HybridCache cache) : IMessageHandler
{
    public string MessageType => "sale.completed";

    public async Task HandleAsync(IntegrationMessage message, CancellationToken cancellationToken)
    {
        var sale = message.Data.Deserialize<SaleCompletedData>(PhpJson.Options)
                   ?? throw new JsonException("sale.completed without data.");

        await cache.RemoveByTagAsync([StatsCacheTags.Event(sale.EventId), StatsCacheTags.Summary], cancellationToken);
    }

    private sealed record SaleCompletedData(long EventId);
}
