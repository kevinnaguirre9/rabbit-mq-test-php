<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$directAndromedaExchange = "andromeda_direct";

$serviceQueueName = "sales.delegate_assignments";
$serviceRetryQueueBindingKey = "sales.delegate_assignments.retry";
$domainErrorQueueBindingKey = "sales.dead_letter";

$connection = new AMQPStreamConnection(
    'app-container-test-rabbitmq',
    5672,
    'guest',
    'guest'
);

$channel = $connection->channel();

echo " [*] Waiting for messages. To exit press CTRL+C\n";

//@see https://github.com/CodelyTV/typescript-ddd-example/blob/master/src/Contexts/Shared/infrastructure/EventBus/RabbitMq/RabbitMQConsumer.ts
//@see https://github.com/CodelyTV/typescript-ddd-example/blob/master/src/Contexts/Shared/infrastructure/EventBus/RabbitMq/RabbitMqConnection.ts
//@see https://docs.particular.net/nservicebus/outbox/
//@see https://milestone.topics.it/2023/02/07/outbox-what-and-why.html?twclid=228aqdrllgqqw3jlaekil8ccdm
//@see https://docs.particular.net/transports/rabbitmq/transactions-and-delivery-guarantees?version=rabbit_6
//@see https://www.rabbitmq.com/docs/confirms

$callback = function (AMQPMessage $message) use ($channel, $directAndromedaExchange, $serviceRetryQueueBindingKey) {
    echo ' [x] Received ', $message->getBody(), "\n";
    
    //Simulate a deadlock in the DB
    //Automatic retries will have been triggered by the time the 10 seconds have passed
    echo "1. Retrying message\n";
    // $processors[$message->type]->handle($message);
    sleep(15);
    echo "End automatic retrying message\n";

    //NOTE: HANDLER PROCESSING LOGIC AND MESSAGE ID INBOX PERSITENCE TRANSACION FAILED

    //Now after automatic retries have been exhausted, we will send the message to retry queue
    //This logic should be in some kind of this.connection.retry($message);
    $originalProperties = $message->get_properties();
    $properties = [
        ...$originalProperties,
        'redelivery_count' => isset($originalProperties['redelivery_count']) ? $originalProperties['redelivery_count'] + 1 : 1,
    ];

    $retryMessage = new AMQPMessage($message->getBody(), $properties);

    //If RabbitMQ is down, the message will be requeued automatically by rabbitmq.
    //Primary queue must be durable and message must be persistent.
    $channel->basic_publish($retryMessage, $directAndromedaExchange, $serviceRetryQueueBindingKey);

    //only then we should acknowledge the message
    //2. But now, what if the retry publish suceeded but the ack failed? (COMMENT THE FIRST SLEEP AND UNCOMMENT THE SECOND SLEEP TO SIMULATE THIS SCENARIO)
    echo "2. Acknowledging message\n";
    // sleep(15);
    $message->ack();
    //ANSEWER: well, now we have duplicate messages in the original queue
};

//Consume the event
$channel->basic_consume(
    $serviceQueueName,
    '',
    false,
    false,
    false,
    false,
    $callback
);


$channel->consume();

// while ($channel->is_open()) {
//     $channel->wait();
// }
