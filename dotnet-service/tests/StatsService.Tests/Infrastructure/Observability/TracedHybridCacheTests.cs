using System.Diagnostics;
using Microsoft.Extensions.Caching.Hybrid;
using Microsoft.Extensions.DependencyInjection;
using StatsService.Api.Infrastructure.Caching;

namespace StatsService.Tests.Infrastructure.Observability;

public class TracedHybridCacheTests
{
    [Fact]
    public async Task Factory_runs_inside_the_caller_trace()
    {
        await using var services = new ServiceCollection().AddHybridCache().Services.BuildServiceProvider();
        var cache = services.GetRequiredService<HybridCache>();

        using var request = new Activity("request").Start();
        // Como RequestAborted: con un token cancelable HybridCache ejecuta la factory desacoplada de quien llama
        using var requestAborted = new CancellationTokenSource();

        var traceInFactory = await cache.GetOrCreateTracedAsync(
            "key",
            0,
            static (_, _) => ValueTask.FromResult(Activity.Current?.TraceId.ToHexString()),
            cancellationToken: requestAborted.Token);

        Assert.Equal(request.TraceId.ToHexString(), traceInFactory);
    }
}
