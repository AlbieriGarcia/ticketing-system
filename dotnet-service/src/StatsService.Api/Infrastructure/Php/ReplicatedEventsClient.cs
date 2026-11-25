using StatsService.Api.Infrastructure.Errors;

namespace StatsService.Api.Infrastructure.Php;

// Decorador: lee de la réplica y solo llama a PHP por HTTP cuando el evento no está o su copia caducó
public sealed class ReplicatedEventsClient(IEventsClient inner, EventReplica replica) : IEventsClient
{
    public async Task<PhpEvent> GetEventAsync(long id, CancellationToken cancellationToken)
    {
        if (replica.TryGet(id, out var phpEvent))
        {
            // La réplica también recibe borradores; se ocultan igual que hace la API pública de PHP
            return phpEvent.Status == "draft" ? throw new EventNotFoundException(id) : phpEvent;
        }

        phpEvent = await inner.GetEventAsync(id, cancellationToken);
        replica.StoreFetched(phpEvent);

        return phpEvent;
    }

    // El listado necesita búsqueda y paginación de PHP; no se replica
    public Task<PhpEventPage> ListEventsAsync(string? search, int page, int perPage, CancellationToken cancellationToken) =>
        inner.ListEventsAsync(search, page, perPage, cancellationToken);
}
