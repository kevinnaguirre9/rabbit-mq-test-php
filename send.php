<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

//Connect to the message broker (RabbitMQ)
$connection = new AMQPStreamConnection('rabbitmq-container-test', 5672, 'guest', 'guest');

//Create a channel
$channel = $connection->channel();

//Declare the Direct type Exchange
$channel->exchange_declare('andromeda', 'direct', durable: true, auto_delete: false);

//Example event message body
$messageBody = json_encode(
    [
        "uuid" => "a8e83147-6957-5936-b508-5723ae47cebf", // event uuid
        "created_at" => "2022-06-29T00 =>10 =>05.938545Z", // date when the event was created
        "video" => [ // Generally it's a good practice that the key of the field describes the entity that is being included in the payload. The data follows the same Model Schema defined in the OpenAPI contract.
            "uuid" => "04af83eb-8840-50ac-a235-f43343e20c86",
            "title" => "IRL in the 21st Century",
            "url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
            "created_at" => "2021-09-02T00 =>15 =>14.140382Z",
        ]
    ]
);

// Create a new message
$AMQPMessage = new AMQPMessage($messageBody, ['content_type' => 'application/json']);

//Publish a message to the exchange
//Then the event will be routed to the QUEUES
// with the routing key 'sales.scholarship-pre-applications.pre_application_registered'
$channel->basic_publish(
    $AMQPMessage,
    'andromeda',
    'my-organization.video.1.event.video.published'
);

echo " [x] Sent $messageBody\n";

$channel->close();
$connection->close();
