<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$fanoutAndromedaExchange = "andromeda_fanout";

$connection = new AMQPStreamConnection(
    'app-container-test-rabbitmq',
    5672,
    'guest',
    'guest'
);

$channel = $connection->channel();

$messageBody = json_encode([
    "information_request_id" => "41cc2792-b92c-480e-9ef7-a5709b51732e",
    "applicant_id" => "7ff3447e-4d32-4991-a89a-003d457b287c",
    "product_id" => "035917a8-85c0-4356-9b18-ecc260da2081",
    "headquarter_id" => "2ab64436-641a-41e0-ac3f-d02732432f55",
    "requested_at" => "2024-09-10T09:50:17.000Z",
]);

$properties = [
    'content_type' => 'application/json',
    'message_id' => '282b1c5a-8551-42d6-9eb3-b77e38f2f654',
    'type' => 'sales.applicants-management.product_information_requested',
    'app_id' => 'applicants-management',
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, //important to set the message as persistent because of rabbitmq server restarts or crashes
];

$AMQPMessage = new AMQPMessage(
    json_encode($messageBody),
    $properties,
);

$channel->basic_publish($AMQPMessage, $fanoutAndromedaExchange);

echo " [x] Sent $messageBody\n";

$channel->close();
$connection->close();