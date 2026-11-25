using StatsService.Api.Infrastructure.Messaging;

namespace StatsService.Api.Features.Sync;

public static class SyncExtensions
{
    // Singletons: los usa el consumidor, que vive durante toda la aplicación
    public static IServiceCollection AddSync(this IServiceCollection services) => services
        .AddSingleton<IMessageHandler, EventChangedHandler>()
        .AddSingleton<IMessageHandler, SaleCompletedHandler>();
}
