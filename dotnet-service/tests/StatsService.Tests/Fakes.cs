using System.Net;
using StatsService.Api.Features.EventStats;
using StatsService.Api.Features.SalesSummary;
using StatsService.Api.Infrastructure.Errors;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Tests;

internal sealed class FakeEventsClient : IEventsClient
{
    public Dictionary<long, PhpEvent> Events { get; } = new();

    public bool IsDown { get; set; }

    public Task<PhpEvent> GetEventAsync(long id, CancellationToken cancellationToken)
    {
        if (IsDown)
        {
            throw new UpstreamUnavailableException("Events service is unavailable.");
        }

        return Events.TryGetValue(id, out var e) ? Task.FromResult(e) : throw new EventNotFoundException(id);
    }

    public Task<PhpEventPage> ListEventsAsync(string? search, int page, int perPage, CancellationToken cancellationToken)
    {
        var items = Events.Values.ToList();

        return Task.FromResult(new PhpEventPage(items, page, perPage, items.Count));
    }
}

internal sealed class FakeEventSalesReader : IEventSalesReader
{
    public EventSalesData Data { get; set; } = new(new SalesTotalsRow(), 0, []);

    public Task<EventSalesData> ReadAsync(long eventId, CancellationToken cancellationToken) => Task.FromResult(Data);
}

internal sealed class FakeSalesSummaryReader : ISalesSummaryReader
{
    public SummaryData Data { get; set; } = new(new SummaryTotalsRow(), [], []);

    public Task<SummaryData> ReadAsync(int topEvents, CancellationToken cancellationToken) => Task.FromResult(Data);
}

internal sealed class FixedTimeProvider(DateTimeOffset now) : TimeProvider
{
    public override DateTimeOffset GetUtcNow() => now;
}

internal sealed class StubHttpHandler(Func<HttpRequestMessage, HttpResponseMessage> respond) : HttpMessageHandler
{
    protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken) =>
        Task.FromResult(respond(request));

    public static StubHttpHandler Json(HttpStatusCode status, string json) => new(_ => new HttpResponseMessage(status)
    {
        Content = new StringContent(json, System.Text.Encoding.UTF8, "application/json"),
    });
}

internal static class TestData
{
    public static readonly DateTimeOffset Now = new(2026, 11, 24, 12, 0, 0, TimeSpan.Zero);

    public static PhpEvent Event(long id = 16, int total = 500, int available = 108, DateTimeOffset? date = null) => new(
        id, "Rock Festival", "Annual", date ?? new DateTimeOffset(2026, 12, 20, 21, 0, 0, TimeSpan.Zero),
        30000m, total, available, "published");
}
