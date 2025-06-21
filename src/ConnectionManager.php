<?php

namespace Linjoe\Amqp;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;

class ConnectionManager
{
    /** @var AMQPStreamConnection[] 缓存的连接对象 */
    protected static array $connections = [];

    /**
     * 获取命名连接实例，如果未创建则新建。
     * @param string $name 连接名称，对应配置项
     * @param array  $config 配置（含 host、port、user、pass、vhost 等）
     * @return AMQPStreamConnection
     * @throws \Exception
     */
    public static function get(string $name, array $config): AMQPStreamConnection
    {
        if (!isset(self::$connections[$name]) || self::$connections[$name]->isConnected() === false) {
            // 建立新连接或重连
            try {
                self::$connections[$name] = new AMQPStreamConnection(
                    $config['host'],
                    $config['port'],
                    $config['username'],
                    $config['password'],
                    $config['vhost'] ?? '/'
                );
            } catch (AMQPRuntimeException $e) {
                throw new \RuntimeException("Failed to connect to RabbitMQ for [$name]: " . $e->getMessage(), 0, $e);
            }
        }
        return self::$connections[$name];
    }

    /**
     * 关闭所有已创建的连接，释放资源。
     */
    public static function closeAll(): void
    {
        foreach (self::$connections as $connection) {
            try {
                $connection->close();
            } catch (\Throwable $e) {
                // 忽略关闭时的异常
            }
        }
        self::$connections = [];
    }
}
