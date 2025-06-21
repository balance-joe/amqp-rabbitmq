<?php
namespace Linjoe\Amqp;

class Events
{
    /**
     * Worker 停止时触发，关闭所有 AMQP 连接。
     */
    public static function onWorkerStop(): void
    {
        ConnectionManager::closeAll();
    }
}
