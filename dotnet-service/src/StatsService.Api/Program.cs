using System.Text.Json;
using StatsService.Api.Features.EventStats;
using StatsService.Api.Features.Events;
using StatsService.Api.Features.SalesSummary;
using StatsService.Api.Features.Sync;
using StatsService.Api.Infrastructure;
using StatsService.Api.Infrastructure.Observability;
using StatsService.Api.Infrastructure.Security;

var builder = WebApplication.CreateBuilder(args);

// Cada log lleva el TraceId/SpanId de la Activity en curso para poder correlacionarlo entre servicios
builder.Logging.Configure(o => o.ActivityTrackingOptions =
    ActivityTrackingOptions.TraceId | ActivityTrackingOptions.SpanId | ActivityTrackingOptions.ParentId);

builder.Services.ConfigureHttpJsonOptions(o => o.SerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower);
builder.Services.AddInfrastructure(builder.Configuration);
builder.Services.AddObservability(builder.Configuration);
builder.Services.AddSwaggerDocs();

builder.Services
    .AddEventStats()
    .AddSalesSummary()
    .AddSync();

var app = builder.Build();

app.UseExceptionHandler();
app.UseSwagger();
app.UseSwaggerUI(o => o.DocumentTitle = "Stats API");
app.UseMiddleware<ApiKeyMiddleware>();

app.MapGet("/health", () => Results.Ok(new { status = "ok" })).ExcludeFromDescription();

var api = app.MapGroup("/api");
ListEventsEndpoint.Map(api);
GetEventEndpoint.Map(api);
GetEventStatsEndpoint.Map(api);
GetSalesSummaryEndpoint.Map(api);

app.Run();

public partial class Program;
