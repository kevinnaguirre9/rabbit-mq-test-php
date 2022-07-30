<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

//Connect to the message broker (RabbitMQ)
$connection = new AMQPStreamConnection(
    'rabbitmq-container-test',
    5672,
    'guest',
    'guest'
);

$channel = $connection->channel();

//Declare the Queue
$channel->queue_declare('user.notification.notify_user_on_video_published', durable: true, auto_delete: false);

//Bind (subscribe) the Queue to the Exchange
$channel->queue_bind(
    'user.notification.notify_user_on_video_published',
    'andromeda',
    'my-organization.video.1.event.video.published'
);

echo " [*] Waiting for messages. To exit press CTRL+C\n";

$callback = function (AMQPMessage $msg) {
    echo ' [x] Received ', $msg->getBody(), "\n";
    $msg->ack();    // Marks the message as delivered (I've consumed it), so it won't be delivered (consumed) again.
};

//Consume the event
$channel->basic_consume(
    'user.notification.notify_user_on_video_published',
    '',
    false,
    false,
    false,
    false,
    $callback);

while ($channel->is_open()) {
    $channel->wait();
}
