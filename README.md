# Rabbitmq message broker test

Rabbitmq dashboard at [http://localhost:15672/](http://localhost:15672/)

## Getting started
	> docker-compose up --build -d
    > docker-compose exec app composer install

### Test producer and consumer
    > docker-compose exec app php send.php
    > docker-compose exec app php receive.php

### Official documentation

https://www.rabbitmq.com/tutorials/tutorial-one-php.html

