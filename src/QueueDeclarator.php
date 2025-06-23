<?php
namespace Linjoe\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Wire\AMQPTable;

class QueueDeclarator
{
    /**
     * 统一封装队列和交换机声明，自动设置 TTL / DLX / max-length
     *
     * @param AMQPChannel $channel
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param array $options
     */
    public static function declare(
        AMQPChannel $channel,
        string $queue,
        string $exchange,
        string $routingKey,
        array $options = []
    ): void {
        $exchangeType = $options['exchange_type'] ?? 'direct';
        $durable = $options['durable'] ?? true;
        $ttl = $options['ttl'] ?? null;
        $dlx = $options['dlx'] ?? null;
        $maxLength = $options['max_length'] ?? null;

        // 构造参数
        $args = [];

        if ($ttl !== null) {
            $args['x-message-ttl'] = (int)$ttl;
        }

        if ($maxLength !== null) {
            $args['x-max-length'] = (int)$maxLength;
        }

        if ($dlx !== null) {
            $args['x-dead-letter-exchange'] = $dlx['exchange'] ?? 'dlx.exchange';
            if (!empty($dlx['routing_key'])) {
                $args['x-dead-letter-routing-key'] = $dlx['routing_key'];
            }
        }

        try {
            // ✅ 判断队列是否已存在（passive 模式）
            [$queueName,,,$queueArgs] = $channel->queue_declare(
                $queue,
                false,
                true,
                false,
                false,
                true, // passive 模式：如果不存在会抛异常
                []
            );

            // ✅ 校验参数是否一致
            $existingArgs = ($queueArgs instanceof AMQPTable) ? $queueArgs->getNativeData() : [];
            foreach ($args as $key => $value) {
                if (isset($existingArgs[$key]) && (string)$existingArgs[$key] !== (string)$value) {
                    throw new \RuntimeException("队列参数冲突：[$key] 当前为 {$existingArgs[$key]}，新设置为 $value");
                }
            }
            // 不一致直接抛出，避免触发 AMQP 错误
        } catch (AMQPProtocolChannelException $e) {
            if (strpos($e->getMessage(), 'NOT_FOUND') !== false) {
                // ✅ 队列不存在，正常 declare（主动声明）
                $channel->queue_declare(
                    $queue,
                    false,
                    $durable,
                    false,
                    false,
                    false,
                    empty($args) ? [] : new AMQPTable($args)
                );
            } else {
                Log::error("Queue declare passive check error: " . $e->getMessage());
                throw $e;
            }
        }

        // ✅ 声明交换机（幂等）
        $channel->exchange_declare($exchange, $exchangeType, false, $durable, false);

        // ✅ 绑定队列和路由
        $channel->queue_bind($queue, $exchange, $routingKey);
    }
}
