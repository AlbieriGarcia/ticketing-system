using System.Diagnostics;

namespace StatsService.Api.Infrastructure.Observability;

public static class Telemetry
{
    public const string SourceName = "StatsService";

    public static readonly ActivitySource Source = new(SourceName);

    // Sin un listener (p. ej. OpenTelemetry) ActivitySource no crea nada; se crea la Activity a mano para que los logs conserven el TraceId
    public static Activity StartActivity(string name, ActivityKind kind, ActivityContext parent = default)
    {
        var activity = Source.StartActivity(name, kind, parent);

        if (activity is not null)
        {
            return activity;
        }

        activity = new Activity(name);

        if (parent != default)
        {
            activity.SetParentId(parent.TraceId, parent.SpanId, parent.TraceFlags);
        }

        return activity.Start();
    }
}
