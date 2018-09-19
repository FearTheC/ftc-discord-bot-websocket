# Discord Bot Websocket
PHP Websocket connecting to Discord server for guilds' events retrieval. As it simply stacks
up events messages into a message queue service (such as RabbitMQ).


<img src="https://docs.fearthec.io/images/ftc-logo.png" alt="Fear The C{ode}" width="400">

# Installation

## Basic install

### PHP requirements
Minimal PHP version : 7.2
Required PHP extensions : bcmath

### External dependencies
The application also requires both an MQ service (i.e. [RabbitMQ](https://www.rabbitmq.com)) and
a KV store (i.e. [Redis](https://redis.io)).


## Docker image 

Ready to go images can be found on the [FTC DockerHub repository](https://hub.docker.com/r/fearthec/ftc-discord-bot-websocket).


# The FTC Discord Bot whole system

![FTC Bot system diagram](https://docs.fearthec.io/images/ftc-bot-system.png)
