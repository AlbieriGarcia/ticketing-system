using System.Net;
using System.Text.Json;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc.Testing;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using StatsService.Api.Features.EventStats;
using StatsService.Api.Features.SalesSummary;
using StatsService.Api.Infrastructure.Messaging;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Tests;

public class ApiTests : IClassFixture<WebApplicationFactory<Program>>
{
    private const string ApiKey = "test-key";

    private readonly FakeEventsClient _events = new();
    private readonly FakeEventSalesReader _sales = new();
    private readonly WebApplicationFactory<Program> _factory;

    public ApiTests(WebApplicationFactory<Program> factory)
    {
        _factory = factory.WithWebHostBuilder(builder =>
        {
            builder.UseSetting("Security:ApiKey", ApiKey);
            builder.UseSetting("PhpApi:BaseUrl", "http://php-api/api");
            builder.UseSetting("ConnectionStrings:Stats", "Host=unused");
            // Sin consumidor: los tests no deben conectarse al broker ni llevarse mensajes reales
            builder.UseSetting("RabbitMq:Enabled", "false");
            builder.ConfigureServices(services =>
            {
                services.RemoveAll<IEventsClient>();
                services.AddSingleton<IEventsClient>(_events);
                services.RemoveAll<IEventSalesReader>();
                services.AddSingleton<IEventSalesReader>(_sales);
                services.RemoveAll<ISalesSummaryReader>();
                services.AddSingleton<ISalesSummaryReader>(new FakeSalesSummaryReader());
            });
        });
    }

    private HttpClient Client(string? apiKey = ApiKey)
    {
        var client = _factory.CreateClient();

        if (apiKey is not null)
        {
            client.DefaultRequestHeaders.Add("X-Api-Key", apiKey);
        }

        return client;
    }

    [Theory]
    [InlineData(null)]
    [InlineData("wrong-key")]
    public async Task Requests_without_valid_api_key_are_rejected(string? apiKey)
    {
        var response = await Client(apiKey).GetAsync("/api/stats/summary");

        Assert.Equal(HttpStatusCode.Unauthorized, response.StatusCode);
    }

    [Fact]
    public async Task Event_stats_returns_snake_case_json()
    {
        _events.Events[16] = TestData.Event();

        var response = await Client().GetAsync("/api/stats/events/16");
        var json = JsonDocument.Parse(await response.Content.ReadAsStringAsync()).RootElement;

        Assert.Equal(HttpStatusCode.OK, response.StatusCode);
        Assert.Equal(500, json.GetProperty("tickets").GetProperty("total").GetInt32());
        Assert.True(json.TryGetProperty("days_until_event", out _));
    }

    [Fact]
    public async Task Unknown_event_returns_problem_details_404()
    {
        var response = await Client().GetAsync("/api/stats/events/999");

        Assert.Equal(HttpStatusCode.NotFound, response.StatusCode);
        Assert.Equal("application/problem+json", response.Content.Headers.ContentType?.MediaType);
    }

    [Fact]
    public async Task Php_down_returns_503()
    {
        _events.IsDown = true;

        var response = await Client().GetAsync("/api/stats/events/16");

        Assert.Equal(HttpStatusCode.ServiceUnavailable, response.StatusCode);
    }

    [Fact]
    public async Task Invalid_query_returns_400()
    {
        var response = await Client().GetAsync("/api/stats/summary?top=100");

        Assert.Equal(HttpStatusCode.BadRequest, response.StatusCode);
    }

    [Fact]
    public async Task Events_list_comes_from_php_client()
    {
        _events.Events[16] = TestData.Event();

        var response = await Client().GetAsync("/api/events?per_page=10");
        var json = JsonDocument.Parse(await response.Content.ReadAsStringAsync()).RootElement;

        Assert.Equal(HttpStatusCode.OK, response.StatusCode);
        Assert.Equal(16, json.GetProperty("items")[0].GetProperty("id").GetInt64());
    }

    [Fact]
    public async Task Events_list_validates_page_size()
    {
        var response = await Client().GetAsync("/api/events?per_page=100");

        Assert.Equal(HttpStatusCode.BadRequest, response.StatusCode);
    }

    [Fact]
    public async Task Sale_message_invalidates_cached_event_stats()
    {
        _events.Events[16] = TestData.Event();
        var client = Client();

        await client.GetAsync("/api/stats/events/16");
        _sales.Data = new EventSalesData(new SalesTotalsRow { Orders = 1, TicketsSold = 4, Revenue = 120m, Customers = 1 }, 0, []);

        Assert.Equal(0, await TicketsSoldAsync(client));

        var handler = _factory.Services.GetServices<IMessageHandler>().Single(h => h.MessageType == "sale.completed");
        var data = JsonDocument.Parse("""{"event_id": 16}""").RootElement;
        await handler.HandleAsync(new IntegrationMessage(Guid.NewGuid(), "sale.completed", 1, TestData.Now, data), CancellationToken.None);

        Assert.Equal(4, await TicketsSoldAsync(client));
    }

    private static async Task<int> TicketsSoldAsync(HttpClient client)
    {
        var json = JsonDocument.Parse(await client.GetStringAsync("/api/stats/events/16")).RootElement;

        return json.GetProperty("tickets").GetProperty("sold").GetInt32();
    }

    [Fact]
    public async Task Swagger_is_public()
    {
        var response = await Client(apiKey: null).GetAsync("/swagger/v1/swagger.json");

        Assert.Equal(HttpStatusCode.OK, response.StatusCode);
    }
}
