using System.Diagnostics;
using System.Text;
using RabbitMQ.Client;
using StatsService.Api.Infrastructure.Messaging;

namespace StatsService.Tests.Infrastructure.Observability;

public class MessageTraceContextTests
{
    [Fact]
    public void Extracts_the_trace_sent_by_php()
    {
        var properties = WithTraceparent("00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01");

        var context = MessageTraceContext.Extract(properties);

        Assert.Equal("4bf92f3577b34da6a3ce929d0e0e4736", context.TraceId.ToHexString());
        Assert.Equal("00f067aa0ba902b7", context.SpanId.ToHexString());
    }

    [Fact]
    public void Missing_or_invalid_header_starts_a_new_trace()
    {
        Assert.Equal(default, MessageTraceContext.Extract(new BasicProperties()));
        Assert.Equal(default, MessageTraceContext.Extract(WithTraceparent("not-a-trace")));
    }

    private static BasicProperties WithTraceparent(string value) => new()
    {
        // Así llegan los headers de texto desde RabbitMQ
        Headers = new Dictionary<string, object?> { [MessageTraceContext.Header] = Encoding.UTF8.GetBytes(value) },
    };
}
