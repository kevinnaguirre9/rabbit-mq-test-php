<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$fanoutAndromedaExchange = "andromeda_fanout";
$directAndromedaExchange = "andromeda_direct";

$serviceQueueName = "sales.delegate_assignments";

$serviceRetryQueueName = "sales.delegate_assignments.retry";
$serviceRetryQueueBindingKey = "sales.delegate_assignments.retry";

$domainErrorQueueName = "sales.dead_letter";
$domainErrorQueueBindingKey = "sales.dead_letter";

//Connect to the message broker (RabbitMQ)
$connection = new AMQPStreamConnection(
    'app-container-test-rabbitmq',
    5672,
    'guest',
    'guest'
);

//Create a channel
$channel = $connection->channel();

//Declare Exchanges
$channel->exchange_declare(
    $fanoutAndromedaExchange, 'fanout', durable: true, auto_delete: false
);

$channel->exchange_declare(
    $directAndromedaExchange, 'direct', durable: true, auto_delete: false
);

//Declare Queues and bind to exchanges

//------- Primary Queue -------
$channel->queue_declare(
    $serviceQueueName,
    durable: true,
    auto_delete: false
);

$channel->queue_bind(
    $serviceQueueName,
    $fanoutAndromedaExchange
);
//------- End Primary Queue -------


//------- Retry Queue -------
$channel->queue_declare(
    $serviceRetryQueueName,
    durable: true,
    auto_delete: false,
    arguments: [
        'x-message-ttl' => ['I', 3000],
        'x-dead-letter-exchange' => ['S', $fanoutAndromedaExchange],
    ]
);

$channel->queue_bind(
    $serviceRetryQueueName,
    $directAndromedaExchange,
    $serviceRetryQueueBindingKey
);
//------- End Retry Queue -------


//------- Domain Error Queue -------
$channel->queue_declare(
    $domainErrorQueueName,
    durable: true,
    auto_delete: false,
);

$channel->queue_bind(
    $domainErrorQueueName,
    $directAndromedaExchange,
    $domainErrorQueueBindingKey
);
//------- End Domain Error Queue -------