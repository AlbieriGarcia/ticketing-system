using System.Net;
using Microsoft.Extensions.Logging.Abstractions;
using StatsService.Api.Infrastructure.Php;
using StatsService.Api.Infrastructure.Errors;

namespace StatsService.Tests.Infrastructure;

public class PhpEventsClientTests
{
    private static PhpEventsClient CreateClient(HttpMessageHandler handler) =>
        new(new HttpClient(handler) { BaseAddress = new Uri("http://php-api/api/") }, NullLogger<PhpEventsClient>.Instance);

    [Fact]
    public async Task Maps_php_event_payload()
    {
        const string json = """
            {"data":{"id":16,"name":"Rock Festival","description":"Annual","event_date":"2026-12-20T21:00:00+00:00",
            "ticket_price":"30000.00","total_tickets":500,"available_tickets":108,"status":"published"}}
            """;
        var client = CreateClient(StubHttpHandler.Json(HttpStatusCode.OK, json));

        var e = await client.GetEventAsync(16, CancellationToken.None);

        Assert.Equal(16, e.Id);
        Assert.Equal(30000.00m, e.TicketPrice);
        Assert.Equal(108, e.AvailableTickets);
        Assert.Equal("published", e.Status);
    }

    [Fact]
    public async Task Maps_paginated_list()
    {
        const string json = """
            {"data":[{"id":1,"name":"A","description":"d","event_date":"2026-12-20T21:00:00+00:00",
            "ticket_price":"10.00","total_tickets":5,"available_tickets":5,"status":"published"}],
            "links":{},"meta":{"current_page":2,"per_page":1,"total":7}}
            """;
        HttpRequestMessage? sent = null;
        var client = CreateClient(new StubHttpHandler(request =>
        {
            sent = request;
            return new HttpResponseMessage(HttpStatusCode.OK) { Content = new StringContent(json) };
        }));

        var page = await client.ListEventsAsync("rock fest", 2, 1, CancellationToken.None);

        Assert.Equal(7, page.Total);
        Assert.Equal(2, page.Page);
        Assert.Single(page.Items);
        Assert.Equal("/api/events?page=2&per_page=1&search=rock%20fest", sent!.RequestUri!.PathAndQuery);
    }

    [Fact]
    public async Task Not_found_becomes_event_not_found()
    {
        var client = CreateClient(StubHttpHandler.Json(HttpStatusCode.NotFound, """{"message":"Resource not found."}"""));

        await Assert.ThrowsAsync<EventNotFoundException>(() => client.GetEventAsync(99, CancellationToken.None));
    }

    [Fact]
    public async Task Server_error_becomes_upstream_unavailable()
    {
        var client = CreateClient(StubHttpHandler.Json(HttpStatusCode.InternalServerError, "{}"));

        await Assert.ThrowsAsync<UpstreamUnavailableException>(() => client.GetEventAsync(1, CancellationToken.None));
    }

    [Fact]
    public async Task Network_failure_becomes_upstream_unavailable()
    {
        var client = CreateClient(new StubHttpHandler(_ => throw new HttpRequestException("Connection refused")));

        await Assert.ThrowsAsync<UpstreamUnavailableException>(() => client.GetEventAsync(1, CancellationToken.None));
    }
}
