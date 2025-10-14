# RabbitMQ + Messenger Bus Implementation for Location Recording

## Overview

This implementation adds asynchronous processing for location recording using RabbitMQ and Symfony Messenger Bus. The location recording process has been refactored to use a queue-based system for better performance, scalability, and reliability.

## Architecture

### Components

1. **RecordLocationMessage** (`src/Message/RecordLocationMessage.php`)
   - Message class representing a location recording job
   - Contains all necessary data for processing location

2. **RecordLocationMessageHandler** (`src/MessageHandler/RecordLocationMessageHandler.php`)
   - Processes location recording messages asynchronously
   - Handles errors and logging

3. **RecordLocationController** (`src/Controller/Agent/RecordLocationController.php`)
   - Modified to dispatch messages instead of synchronous processing
   - Returns immediate response (202 Accepted)

4. **Queue Configuration** (`config/packages/messenger.yaml`)
   - Dedicated `location_queue` transport for location messages
   - RabbitMQ exchange and routing configuration
   - Retry strategy for failed messages

## Queue Configuration

### Transport Setup

```yaml
location_queue:
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    options:
        exchange:
            name: location_exchange
            type: direct
        queues:
            location_queue:
                binding_keys: [location.record]
    retry_strategy:
        max_retries: 5
        delay: 1000
        multiplier: 2
        max_delay: 30000
```

### Environment Configuration

Set in `.env`:
```
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages
```

## Docker Setup

RabbitMQ service added to `docker-compose.yml`:

```yaml
rabbitmq:
    image: rabbitmq:3-management-alpine
    container_name: symfony_rabbitmq
    environment:
        RABBITMQ_DEFAULT_USER: guest
        RABBITMQ_DEFAULT_PASS: guest
    ports:
        - "5672:5672"    # AMQP port
        - "15672:15672"  # Management UI port
```

## Usage

### Starting the System

1. **Start Docker services:**
   ```bash
   docker-compose up -d
   ```

2. **Start message consumers:**
   ```bash
   php bin/console messenger:consume location_queue -vv
   ```

### Monitoring

1. **Queue Status Command:**
   ```bash
   php bin/console app:queue:status
   ```

2. **RabbitMQ Management UI:**
   - URL: http://localhost:15672
   - Username: guest
   - Password: guest

3. **Symfony Messenger Stats:**
   ```bash
   php bin/console messenger:stats
   ```

## Flow Diagram

```
Agent App → RecordLocationController → Message Dispatch → RabbitMQ Queue
                     ↓ (202 Accepted)
                Client receives confirmation

                                    RabbitMQ Queue → MessageHandler → AgentLocationService
                                                                            ↓
                                                                      Database Storage
                                                                            ↓
                                                                    Mercure Broadcast
```

## Error Handling

### Exception Types

1. **UnrecoverableMessageHandlingException**
   - Used for validation errors that won't resolve with retry
   - Messages are moved to failed queue immediately

2. **LocationRecordingException**
   - Custom exception for location recording failures
   - Includes context (user ID, task ID, coordinates)
   - Allows for retries based on retry strategy

### Retry Strategy

- **Max Retries:** 5
- **Initial Delay:** 1 second
- **Multiplier:** 2 (exponential backoff)
- **Max Delay:** 30 seconds

### Failed Message Handling

Failed messages are routed to the `failed` transport (Doctrine-based):
```bash
php bin/console messenger:failed:show
php bin/console messenger:failed:retry
```

## Performance Benefits

1. **Non-blocking API Response**
   - Controller returns immediately (202 Accepted)
   - Location processing happens asynchronously

2. **Scalability**
   - Multiple workers can process messages in parallel
   - Queue handles high traffic bursts

3. **Reliability**
   - Messages persist in RabbitMQ if worker crashes
   - Automatic retry for transient failures
   - Dead letter queue for permanent failures

## Monitoring and Logging

### Log Levels

- **INFO:** Normal processing events
- **WARNING:** Validation errors, recoverable issues
- **ERROR:** Processing failures, system errors

### Key Metrics to Monitor

1. **Queue Depth:** Number of pending messages
2. **Processing Rate:** Messages processed per second
3. **Error Rate:** Failed message percentage
4. **Processing Duration:** Time from dispatch to completion

### Health Checks

Monitor these endpoints/commands:
- RabbitMQ Management UI
- `app:queue:status` command
- Application logs
- Database connection status

## Deployment Considerations

### Production Setup

1. **RabbitMQ Clustering**
   - Set up RabbitMQ cluster for high availability
   - Configure proper persistence and replication

2. **Worker Scaling**
   - Run multiple consumer processes
   - Use process managers (systemd, supervisor)

3. **Monitoring**
   - Set up alerts for queue depth
   - Monitor processing rates and errors
   - Use APM tools for performance tracking

### Environment Variables

```bash
# Production RabbitMQ
MESSENGER_TRANSPORT_DSN=amqp://user:password@rabbitmq-cluster:5672/%2f/messages

# Consumer scaling
MESSENGER_CONSUMER_COUNT=4
```

## Rollback Strategy

If issues occur, you can temporarily revert to synchronous processing:

1. Update routing in `messenger.yaml`:
   ```yaml
   App\Message\RecordLocationMessage: sync
   ```

2. Or modify controller to call service directly:
   ```php
   // Temporary fallback in controller
   $this->agentLocationService->processLocationRequest($idcryptuser, $request->getContent());
   ```

## Testing

### Unit Tests

Test message creation and handling:
```bash
php bin/phpunit tests/Unit/Message/
php bin/phpunit tests/Unit/MessageHandler/
```

### Integration Tests

Test complete queue flow:
```bash
php bin/phpunit tests/Integration/Queue/
```

### Load Testing

Use tools like Apache Bench or Artillery to test queue performance under load.