namespace StatsService.Api.Infrastructure.Php;

public interface IEventsClient
{
    Task<PhpEvent> GetEventAsync(long id, CancellationToken cancellationToken);

    Task<PhpEventPage> ListEventsAsync(string? search, int page, int perPage, CancellationToken cancellationToken);
}
