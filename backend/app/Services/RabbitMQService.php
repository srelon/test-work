<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMQService
{
    protected ?AMQPStreamConnection $connection = null;

    protected ?AMQPChannel $channel = null;

    public function publish(string $queue, array $payload): void {
        try {
            $channel = $this->channel();
            $channel->queue_declare($queue, false, true, false, false);

            $message = new AMQPMessage(
                json_encode($payload),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT],
            );

            $channel->basic_publish($message, '', $queue);
        } catch (Throwable $e) {
            $this->channel = null;
            $this->connection = null;

            throw $e;
        }
    }

    public function consume(string $queue, callable $handler): void {
        $channel = $this->channel();
        $channel->queue_declare($queue, false, true, false, false);
        $channel->basic_qos(0, 1, false);

        $channel->basic_consume($queue, '', false, false, false, false, function (AMQPMessage $message) use ($handler) {
            try {
                $handler(json_decode($message->getBody(), true));
                $message->ack();
            } catch (Throwable $e) {
                report($e);
                $message->nack(false);
            }
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    protected function channel(): AMQPChannel {
        if ($this->channel === null) {
            $config = config('rabbitmq');

            $this->connection = new AMQPStreamConnection(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['vhost'],
            );

            $this->channel = $this->connection->channel();
        }

        return $this->channel;
    }

    public function __destruct() {
        $this->channel?->close();
        $this->connection?->close();
    }
}
