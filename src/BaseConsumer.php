<?php
namespace Linjoe\Amqp;

use PhpAmqpLib\Message\AMQPMessage;

abstract class BaseConsumer implements ConsumerInterface
{
    /** 最大重试次数 */
    protected int $maxRetries = 3;

    public function consume(AMQPMessage $msg): void
    {
        var_dump('BaseConsumer[consume');
        try {
            $this->handle($msg->getBody());
            $msg->getChannel()->basic_ack($msg->getDeliveryTag());
        } catch (\Throwable $e) {
            $retryCount = $this->extractRetryCount($msg);

            if ($retryCount < $this->getMaxRetries()) {
                $msg->getChannel()->basic_nack($msg->getDeliveryTag(), false, true);
            } else {
                $msg->getChannel()->basic_reject($msg->getDeliveryTag(), false);
            }
        }
    }

    abstract protected function handle(string $body): void;

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    protected function extractRetryCount(AMQPMessage $msg): int
    {
        $headers = $msg->get('application_headers');
        if ($headers && isset($headers->getNativeData()['x-death'][0]['count'])) {
            return (int) $headers->getNativeData()['x-death'][0]['count'];
        }
        return 0;
    }
}
