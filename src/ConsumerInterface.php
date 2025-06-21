<?php
namespace Linjoe\Amqp;

use PhpAmqpLib\Message\AMQPMessage;

interface ConsumerInterface
{
    /**
     * 处理消息入口
     * @param AMQPMessage $msg
     * @return void
     */
    public function consume(AMQPMessage $msg): void;
}
