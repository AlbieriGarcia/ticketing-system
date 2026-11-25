<?php

namespace App\Messaging;

use App\Models\OutboxMessage;
use Illuminate\Support\Collection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

class AmqpPublisher implements MessagePublisher
{
    private const CONFIRM_TIMEOUT_SECONDS = 5;

    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    private bool $nacked = false;

    public function __construct(private readonly array $config) {}

    public function publish(Collection $messages): void
    {
        try {
            $channel = $this->channel();
            $this->nacked = false;

            foreach ($messages as $message) {
                $channel->basic_publish($this->toAmqp($message), $this->config['exchange'], $message->type);
            }

            // Publisher confirms: solo se da por publicado lo que el broker confirmó haber recibido
            $channel->wait_for_pending_acks(self::CONFIRM_TIMEOUT_SECONDS);

            if ($this->nacked) {
                throw new RuntimeException('RabbitMQ rejected at least one message of the batch.');
            }
        } catch (Throwable $e) {
            // Una conexión fallida no se reutiliza: el próximo intento abre una nueva
            $this->disconnect();

            throw $e;
        }
    }

    public function disconnect(): void
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (Throwable) {
            // La conexión ya estaba rota; no hay nada más que cerrar
        }

        $this->channel = null;
        $this->connection = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel?->is_open()) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost'],
        );

        $this->channel = $this->connection->channel();
        $this->channel->confirm_select();
        $this->channel->set_nack_handler(function () {
            $this->nacked = true;
        });

        // Declarar es idempotente: cada lado declara lo que necesita y no depende del orden de arranque
        $this->channel->exchange_declare($this->config['exchange'], AMQPExchangeType::TOPIC, durable: true, auto_delete: false);

        return $this->channel;
    }

    private function toAmqp(OutboxMessage $message): AMQPMessage
    {
        $body = json_encode([
            'message_id' => $message->message_id,
            'type' => $message->type,
            'sequence' => $message->id,
            'occurred_at' => $message->created_at->toIso8601String(),
            'data' => $message->payload,
        ], JSON_THROW_ON_ERROR);

        return new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $message->message_id,
            'type' => $message->type,
            'timestamp' => $message->created_at->getTimestamp(),
        ]);
    }
}
