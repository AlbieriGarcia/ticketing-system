using System.Text.Json;
using Microsoft.Extensions.Options;
using RabbitMQ.Client;
using RabbitMQ.Client.Events;
using StatsService.Api.Infrastructure.Php;

namespace StatsService.Api.Infrastructure.Messaging;

public sealed class RabbitMqConsumer(
    IOptions<RabbitMqOptions> options,
    IEnumerable<IMessageHandler> handlers,
    ILogger<RabbitMqConsumer> logger) : BackgroundService
{
    private const ushort PrefetchCount = 20;
    private const int MaxBackoffSeconds = 30;

    private readonly RabbitMqOptions _options = options.Value;
    private readonly Dictionary<string, IMessageHandler> _handlers = handlers.ToDictionary(h => h.MessageType);
    private int _failedAttempts;

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        // El broker puede no estar listo o caerse: se reintenta sin tumbar la API, que sigue sirviendo con su fallback HTTP
        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                await ConsumeUntilDisconnectedAsync(stoppingToken);
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                _failedAttempts++;
                var delay = TimeSpan.FromSeconds(Math.Min(MaxBackoffSeconds, Math.Pow(2, _failedAttempts)));
                logger.LogWarning(ex, "RabbitMQ unavailable, retrying in {Delay}", delay);

                try
                {
                    await Task.Delay(delay, stoppingToken);
                }
                catch (OperationCanceledException)
                {
                    break;
                }
            }
        }
    }

    private async Task ConsumeUntilDisconnectedAsync(CancellationToken cancellationToken)
    {
        var factory = new ConnectionFactory
        {
            HostName = _options.Host,
            Port = _options.Port,
            UserName = _options.UserName,
            Password = _options.Password,
            VirtualHost = _options.VirtualHost,
            ClientProvidedName = "stats-service",
            // La reconexión la gestiona este bucle, así el estado queda explícito en un solo lugar
            AutomaticRecoveryEnabled = false,
        };

        await using var connection = await factory.CreateConnectionAsync(cancellationToken);
        await using var channel = await connection.CreateChannelAsync(cancellationToken: cancellationToken);

        var closed = new TaskCompletionSource(TaskCreationOptions.RunContinuationsAsynchronously);
        connection.ConnectionShutdownAsync += (_, _) =>
        {
            closed.TrySetResult();
            return Task.CompletedTask;
        };

        await DeclareTopologyAsync(channel, cancellationToken);
        await channel.BasicQosAsync(prefetchSize: 0, PrefetchCount, global: false, cancellationToken);

        var consumer = new AsyncEventingBasicConsumer(channel);
        consumer.ReceivedAsync += (_, delivery) => HandleDeliveryAsync(channel, delivery, cancellationToken);
        await channel.BasicConsumeAsync(_options.Queue, autoAck: false, consumer, cancellationToken);

        _failedAttempts = 0;
        logger.LogInformation("Consuming messages from queue {Queue}", _options.Queue);

        await closed.Task.WaitAsync(cancellationToken);
        logger.LogWarning("RabbitMQ connection closed, reconnecting");
    }

    // El consumidor es dueño de su cola: la declara junto con la dead-letter queue donde terminan los mensajes que fallan
    private async Task DeclareTopologyAsync(IChannel channel, CancellationToken cancellationToken)
    {
        var deadLetterExchange = $"{_options.Queue}.dlx";
        var deadLetterQueue = $"{_options.Queue}.dlq";

        await channel.ExchangeDeclareAsync(_options.Exchange, ExchangeType.Topic, durable: true, cancellationToken: cancellationToken);
        await channel.ExchangeDeclareAsync(deadLetterExchange, ExchangeType.Fanout, durable: true, cancellationToken: cancellationToken);

        await channel.QueueDeclareAsync(deadLetterQueue, durable: true, exclusive: false, autoDelete: false, cancellationToken: cancellationToken);
        await channel.QueueBindAsync(deadLetterQueue, deadLetterExchange, routingKey: string.Empty, cancellationToken: cancellationToken);

        await channel.QueueDeclareAsync(
            _options.Queue,
            durable: true,
            exclusive: false,
            autoDelete: false,
            arguments: new Dictionary<string, object?> { ["x-dead-letter-exchange"] = deadLetterExchange },
            cancellationToken: cancellationToken);

        foreach (var messageType in _handlers.Keys)
        {
            await channel.QueueBindAsync(_options.Queue, _options.Exchange, routingKey: messageType, cancellationToken: cancellationToken);
        }
    }

    private async Task HandleDeliveryAsync(IChannel channel, BasicDeliverEventArgs delivery, CancellationToken cancellationToken)
    {
        try
        {
            var message = JsonSerializer.Deserialize<IntegrationMessage>(delivery.Body.Span, PhpJson.Options)
                          ?? throw new JsonException("Empty message body.");

            if (_handlers.TryGetValue(message.Type, out var handler))
            {
                await handler.HandleAsync(message, cancellationToken);
            }
            else
            {
                logger.LogWarning("Ignoring message {MessageId} of unknown type {Type}", message.MessageId, message.Type);
            }

            await channel.BasicAckAsync(delivery.DeliveryTag, multiple: false, cancellationToken);
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            // Sin ack: al cerrar el canal el broker lo devuelve a la cola
        }
        catch (Exception ex)
        {
            // Sin requeue para no reintentar en bucle un mensaje que siempre falla; queda en la DLQ para revisarlo
            logger.LogError(ex, "Message {MessageId} failed and was dead-lettered", delivery.BasicProperties.MessageId);
            await channel.BasicNackAsync(delivery.DeliveryTag, multiple: false, requeue: false, cancellationToken);
        }
    }
}
