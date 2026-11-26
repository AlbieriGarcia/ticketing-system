using System.Diagnostics;
using System.Text;
using RabbitMQ.Client;

namespace StatsService.Api.Infrastructure.Messaging;

public static class MessageTraceContext
{
    public const string Header = "traceparent";

    // RabbitMQ entrega los headers de texto como byte[]; un header ausente o inválido deja empezar una traza nueva
    public static ActivityContext Extract(IReadOnlyBasicProperties properties)
    {
        if (properties.Headers?.TryGetValue(Header, out var value) == true
            && value is byte[] bytes
            && ActivityContext.TryParse(Encoding.UTF8.GetString(bytes), traceState: null, out var context))
        {
            return context;
        }

        return default;
    }
}
