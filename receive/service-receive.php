<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire;

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

$callback = function (AMQPMessage $message) use (
    $channel, $directAndromedaExchange, $serviceRetryQueueBindingKey, $domainErrorQueueBindingKey) {
    
    echo '[INFO] Received message', $message->getBody(), "\n";

    //$processor = $processors[$message->type];

    //FIRST STEP MUST BE DEDUPLICATION
    //e.g. SELECT * FROM inbox_messages WHERE message_id = $message->get('message_id') 
    //AND handler = getClass($processor);

    //IF MESSAGE HAS NOT BEEN HANDLED YET, THEN HANDLE IT.
    
    //Simulate a deadlock in the DB while handling the message
    //Automatic retries will have been triggered by the time the 10 seconds have passed
    echo "[INFO] Starting Automatic Message Retries\n";
    // $processor->handle($message); //OUTBOX PATTERN MUST BE USED INSIDE THE HANDLER.
    sleep(4);
    echo "[INFO] End Automatic Message Retries\n";

    //NOTE: HANDLER PROCESSING LOGIC AND MESSAGE ID INBOX PERSITENCE TRANSACION FAILED

    //Now after automatic retries have been exhausted...
    //we will either send the message to Retry Queue and back to the Primary Queue
    //or direclt to the Erro Queue if we have exhausted the redeliveries. 
    
    //NOTE: This logic should be in some kind of this.connection.retry($message);
    $originalProperties = $message->get_properties();

    $redeliveryCount = $originalProperties['application_headers']['redelivery_count'] ?? 0;


    echo '----------------------------------------';
    echo "[INFO] Redelivery Count: " . $redeliveryCount . "\n";
    echo '----------------------------------------';
    // var_dump($originalProperties['application_headers']?->getNativeData());


    if ($redeliveryCount > 3) {
        echo "[INFO] Sending Message to Error Queue\n";

        $properties = [
            ...$originalProperties,
            'application_headers' => new Wire\AMQPTable([
                'exception' => 'Database lock exception.',
            ]),
        ];

        $poissonMessage = new AMQPMessage($message->getBody(), $properties);

        $channel->basic_publish($poissonMessage, $directAndromedaExchange, $domainErrorQueueBindingKey);
    
    } else {
        
        echo "[INFO] Sending Message to Retry Queue\n";
        
        $properties = [
            ...$originalProperties,
            'application_headers' => new Wire\AMQPTable([
                'redelivery_count' => $redeliveryCount + 1,
            ]),
        ];

        $retryMessage = new AMQPMessage($message->getBody(), $properties);

        //If RabbitMQ is down, the message will be requeued automatically by rabbitmq.
        //Primary queue must be durable and message must be persistent.
        $channel->basic_publish($retryMessage, $directAndromedaExchange, $serviceRetryQueueBindingKey);
    }

    //Only then we should Acknowledge the Message
    echo "[INFO] Acknowledging Message\n";
    // sleep(15);
    $message->ack();

    //What If the retry/poisson publish suceeded but the ack failed? (COMMENT THE FIRST SLEEP AND UNCOMMENT THE SECOND SLEEP TO SIMULATE THIS SCENARIO)
    //ANSEWER: well, now we have duplicate messages: one the primary queue and one on the error queue.
    //On the worst cases, we'd have the same message twice on the error queue.
    //Or meaybe the message was finally processeded after rabbitmq requeue automatically the message and redelivered to the endpoint.
    
    //When we'll republish the message from the RabbitMQ error queue to the primary queue, the endpoint will say that the message is a duplicate and was
    //already processed processed. That is why DEDUPLICATION is important!
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
