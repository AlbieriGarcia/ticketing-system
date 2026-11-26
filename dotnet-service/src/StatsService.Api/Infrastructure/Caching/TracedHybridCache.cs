using System.Diagnostics;
using Microsoft.Extensions.Caching.Hybrid;
using StatsService.Api.Infrastructure.Observability;

namespace StatsService.Api.Infrastructure.Caching;

public static class TracedHybridCache
{
    // Con un token cancelable (RequestAborted) HybridCache ejecuta la factory desacoplada de quien llama, para que una cancelación
    // no aborte la carga compartida; la traza se perdería justo en la llamada a PHP, así que se captura antes y se continúa dentro
    public static ValueTask<T> GetOrCreateTracedAsync<TState, T>(
        this HybridCache cache,
        string key,
        TState state,
        Func<TState, CancellationToken, ValueTask<T>> factory,
        IEnumerable<string>? tags = null,
        CancellationToken cancellationToken = default)
    {
        var parent = Activity.Current?.Context ?? default;

        return cache.GetOrCreateAsync(
            key,
            (state, factory, parent, key),
            static async (s, ct) =>
            {
                using var activity = Telemetry.StartActivity("cache populate", ActivityKind.Internal, s.parent);
                activity.SetTag("cache.key", s.key);

                return await s.factory(s.state, ct);
            },
            tags: tags,
            cancellationToken: cancellationToken);
    }
}
