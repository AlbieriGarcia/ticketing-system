using System.Net;
using StatsService.Api.Infrastructure.Errors;

namespace StatsService.Api.Infrastructure.Php;

public sealed class PhpEventsClient(HttpClient httpClient, ILogger<PhpEventsClient> logger) : IEventsClient
{
    public async Task<PhpEvent> GetEventAsync(long id, CancellationToken cancellationToken)
    {
        var response = await SendAsync($"events/{id}", cancellationToken);

        if (response.StatusCode == HttpStatusCode.NotFound)
        {
            throw new EventNotFoundException(id);
        }

        var body = await ReadAsync<DataEnvelope<PhpEvent>>(response, cancellationToken);

        return body.Data;
    }

    public async Task<PhpEventPage> ListEventsAsync(string? search, int page, int perPage, CancellationToken cancellationToken)
    {
        var query = $"events?page={page}&per_page={perPage}";

        if (!string.IsNullOrWhiteSpace(search))
        {
            query += $"&search={Uri.EscapeDataString(search)}";
        }

        var response = await SendAsync(query, cancellationToken);
        var body = await ReadAsync<PagedEnvelope>(response, cancellationToken);

        return new PhpEventPage(body.Data, body.Meta.CurrentPage, body.Meta.PerPage, body.Meta.Total);
    }

    private async Task<HttpResponseMessage> SendAsync(string uri, CancellationToken cancellationToken)
    {
        try
        {
            return await httpClient.GetAsync(uri, cancellationToken);
        }
        // Incluye errores de red, timeouts y circuito abierto de Polly; se excluye la cancelación del propio cliente
        catch (Exception ex) when (!cancellationToken.IsCancellationRequested)
        {
            logger.LogError(ex, "PHP API unreachable when calling {Uri}", uri);
            throw new UpstreamUnavailableException("Events service is unavailable.", ex);
        }
    }

    private async Task<T> ReadAsync<T>(HttpResponseMessage response, CancellationToken cancellationToken)
    {
        if (!response.IsSuccessStatusCode)
        {
            logger.LogError("PHP API responded {StatusCode} for {Uri}", (int)response.StatusCode, response.RequestMessage?.RequestUri);
            throw new UpstreamUnavailableException($"Events service responded with status {(int)response.StatusCode}.");
        }

        return await response.Content.ReadFromJsonAsync<T>(PhpJson.Options, cancellationToken)
               ?? throw new UpstreamUnavailableException("Events service returned an empty response.");
    }

    private sealed record DataEnvelope<T>(T Data);

    private sealed record PagedEnvelope(IReadOnlyList<PhpEvent> Data, PageMeta Meta);

    private sealed record PageMeta(int CurrentPage, int PerPage, int Total);
}
