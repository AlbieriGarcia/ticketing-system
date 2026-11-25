using System.Text.Json;

namespace StatsService.Api.Infrastructure.Messaging;

// Sobre común de los mensajes que publica el outbox de PHP
public sealed record IntegrationMessage(
    Guid MessageId,
    string Type,
    long Sequence,
    DateTimeOffset OccurredAt,
    JsonElement Data);

public interface IMessageHandler
{
    string MessageType { get; }

    Task HandleAsync(IntegrationMessage message, CancellationToken cancellationToken);
}
