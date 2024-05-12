<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$domainErrorQueueName = "sales.dead_letter";

$connection = new AMQPStreamConnection(
    'app-container-test-rabbitmq',
    5672,
    'guest',
    'guest'
);

$channel = $connection->channel();

echo " [*] Waiting for poisson messages. To exit press CTRL+C\n";

$callback = function (AMQPMessage $msg) {
    echo ' [x] Received ', $msg->getBody(), "\n";
    $msg->ack();
    // $msg->getChannel()->stopConsume();  // Stops the consumer (the script).
};

//Consume the event
$channel->basic_consume(
    $domainErrorQueueName,
    '',
    false,
    false,
    false,
    false,
    $callback
);


$channel->consume();
