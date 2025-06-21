<?php
namespace Linjoe\Amqp;

use http\Exception\RuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use support\Log;

class ConsumerRunner
{
    protected array $consumers = [];

    public function __construct(array $consumers = [])
    {
        $this->consumers = $consumers ?: [
            'email.send' => \app\Consumer\EmailConsumer::class,
            // 添加更多 queue => ConsumerClass
        ];
        var_dump($this->consumers);
    }

    public function onWorkerStart(): void
    {
        foreach ($this->consumers as $queue => $consumerClass) {
            \Workerman\Timer::add(0.001, function () use ($queue, $consumerClass) {
                $this->startConsume($queue, $consumerClass);
            }, [], false);
        }
    }

    protected function startConsume(string $queue, string $consumerClass): void
    {
        /** @var BaseConsumer $consumer */
        $consumer = new $consumerClass();

        $config = config('rabbitmq.connections.default');
        if (!$config){
            throw new RuntimeException("AMQP消费异常:配置不存在 ({$queue}): " .json_encode($config) );
        }

        $connection = ConnectionManager::get('default', $config);
        $channel = $connection->channel();

        $channel->queue_declare($queue, false, true, false, false);

        $channel->basic_qos(0, 1, false); // 每次只分发一个消息

        var_dump("监听的queue：   $queue");
        $channel->basic_consume($queue, '', false, false, false, false,
            function (AMQPMessage $msg) use ($consumer) {
                try {
                    $consumer->consume($msg); // 统一交由 BaseConsumer::consume 处理
                } catch (\Throwable $e) {
                    var_dump("AMQP 消费异常：{$e->getMessage()} ");
                    \support\Log::error("AMQP 消费异常：{$e->getMessage()}");
                }
            }
        );

        while (count($channel->callbacks)) {
            try {
                $channel->wait();
            } catch (\Throwable $e) {
                var_dump("AMQP消费循环异常：{$e->getMessage()} ");

                Log::error("AMQP消费循环异常 ({$queue}): " . $e->getMessage());
                break;
            }
        }
    }

    /**
     * 提取 x-death 或自定义 headers 中的重试次数
     */
    protected function extractRetryCount(AMQPMessage $msg): int
    {
        $headers = $msg->has('application_headers')
            ? $msg->get('application_headers')->getNativeData()
            : [];

        if (isset($headers['x-death'][0]['count'])) {
            return (int) $headers['x-death'][0]['count'];
        }

        if (isset($headers['x-retry'])) {
            return (int) $headers['x-retry'];
        }

        return 0;
    }
}
