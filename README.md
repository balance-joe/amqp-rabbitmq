# AMQP RabbitMQ 客户端

一个轻量级、高性能的 RabbitMQ 客户端，专为 Webman 常驻内存框架设计，支持连接复用、自动重连、消息封装、异常处理等企业级特性。

## 特性

- 轻量化、低侵入设计
- 连接复用与自动重连机制
- 生命周期钩子管理（onWorkerStart / onWorkerStop）
- 消息封装与标准化
- 简洁的发布与消费 API
- 异常处理、重试与死信策略
- 插件化扩展（延迟队列、限流、链路追踪、监控）

## 安装

```bash
composer require linjoe/amqp-rabbitmq
```

## 快速开始

### 配置

1. 创建配置文件 `config/rabbitmq.php`：

```php
return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'host' => 'localhost',
            'port' => 5672,
            'username' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'heartbeat' => 60,
        ],
    ],
];
```

2. 在 `config/process.php` 中注册消费者进程：

```php
return [
    'consumer' => [
        'handler' => Linjoe\Amqp\ConsumerRunner::class,
        'count' => 1,
    ],
];
```

### 发布消息

```php
use Linjoe\Amqp\ConnectionManager;
use Linjoe\Amqp\Message\Message;
use Linjoe\Amqp\Publisher;

// 创建消息
$message = new Message(
    json_encode(['content' => 'Hello World']),  // 消息体
    'amq.direct',                               // 交换机
    'my_queue',                                 // 路由键
    Message::DELIVERY_MODE_PERSISTENT,          // 持久化
    ['content_type' => 'application/json']      // 头信息
);

// 获取配置
$config = config('rabbitmq.connections.default');

// 发布消息
$publisher = new Publisher('default');
$publisher->publish(ConnectionManager::getInstance(), $message);
```

### 创建消费者

1. 创建消费者类：

```php
namespace App\Consumer;

use Linjoe\Amqp\BaseConsumer;

class MyConsumer extends BaseConsumer
{
    protected int $maxRetries = 3;
    
    protected function handle(string $body): void
    {
        $data = json_decode($body, true);
        // 处理消息...
    }
}
```

2. 注册消费者：

在 `config/process.php` 中的 `options` 部分添加：

```php
'options' => [
    'consumers' => [
        'my_queue' => App\Consumer\MyConsumer::class,
    ],
],
```

## 高级功能

### 延迟队列

```php
use Linjoe\Amqp\Plugin\DelayedQueue;

$delayer = new DelayedQueue('default');
$delayer->initialize($config);
$delayer->publishDelayed($message, 60000, $config); // 延迟 60 秒
```

### 链路追踪

```php
use Linjoe\Amqp\Plugin\Tracing;

$tracing = new Tracing('my-service');
$tracedMessage = $tracing->tracePublish($message);
```

### 限流控制

```php
use Linjoe\Amqp\Plugin\RateLimiter;

$limiter = new RateLimiter(10, 20); // 每秒 10 条消息，突发最多 20 条
if ($limiter->acquire()) {
    // 处理消息...
}
```

### 监控与告警

```php
use Linjoe\Amqp\Plugin\Monitoring;

$monitoring = new Monitoring('default');
$monitoring->setAlertThresholds([
    'queue_length' => 1000,
    'consume_rate' => 1.0,
]);
$monitoring->setAlertCallback(function($type, $data) {
    // 发送告警...
});
```

## 最佳实践

- 使用 `channel->close()` 而不是 `connection->close()` 以保持连接复用
- 合理设置重试次数和死信队列，避免消息丢失
- 使用监控插件及时发现队列积压和消费异常
- 在高并发场景下使用限流插件避免消费雪崩

## 许可证

MIT