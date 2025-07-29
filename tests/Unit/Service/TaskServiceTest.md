# TaskService Unit Tests

## Overview
This file contains comprehensive unit tests for the `TaskService` class, covering all the public and private methods related to agent assignment functionality.

## Test Coverage

### 1. **Successful Agent Assignment** (`testAssignAgentsToOrderSuccess`)
- Tests the complete happy path of assigning multiple agents to a service order
- Verifies that tasks are created correctly with proper coordinates
- Ensures database transactions work properly

### 2. **Service Order Not Found** (`testAssignAgentsToOrderThrowsExceptionWhenServiceOrderNotFound`)
- Tests error handling when an invalid service order ID is provided
- Verifies proper exception message

### 3. **Agent Not Found** (`testAssignAgentsToOrderThrowsExceptionWhenAgentNotFound`)
- Tests error handling when an invalid agent ID is provided
- Ensures the encrypted agent ID is included in the error message

### 4. **Agent Not Available** (`testAssignAgentsToOrderThrowsExceptionWhenAgentNotAvailable`)
- Tests business logic that prevents assigning already busy agents
- Verifies agent availability checking works correctly

### 5. **Invalid Coordinates** (`testAssignAgentsToOrderThrowsExceptionForInvalidCoordinates`)
- Tests coordinate validation (must be exactly 2 numeric values)
- Ensures proper error messages for invalid coordinate formats

### 6. **Missing Assignment Fields** (`testAssignAgentsToOrderThrowsExceptionForMissingAssignmentFields`)
- Tests validation of assignment structure
- Ensures both `agentId` and `coordinates` are required

### 7. **Database Transaction Rollback** (`testAssignAgentsToOrderRollsBackOnException`)
- Tests that database transactions are properly rolled back on errors
- Simulates database exceptions during the flush operation

### 8. **Get Tasks by Order** (`testGetTasksByOrder`)
- Tests the simple repository delegation for fetching tasks by service order

### 9. **Get Tasks by Agent** (`testGetTasksByAgent`)
- Tests the simple repository delegation for fetching tasks by agent

## Running the Tests

### Run only TaskService tests:
```bash
./vendor/bin/phpunit tests/Unit/Service/TaskServiceTest.php
```

### Run all service tests:
```bash
./vendor/bin/phpunit tests/Unit/Service/
```

### Run with coverage (if configured):
```bash
./vendor/bin/phpunit tests/Unit/Service/TaskServiceTest.php --coverage-text
```

## Test Architecture

### Mocking Strategy
- **Repositories**: All repository dependencies are mocked to isolate business logic
- **CryptService**: Mocked to control encryption/decryption behavior
- **EntityManager**: Mocked to test transaction behavior
- **Entities**: Mock entities are created with controlled return values

### Assertions
- **Type checking**: Ensures returned values are of correct types
- **Count verification**: Verifies correct number of tasks are created
- **Exception testing**: Validates specific exception types and messages
- **Method call verification**: Ensures correct repository/service methods are called

### Helper Methods
- `createMockServiceOrder()`: Creates a mock ServiceOrders entity
- `createMockAgent()`: Creates a mock Agents entity with associated User

## Coverage
The tests cover:
- ✅ All public methods
- ✅ All private validation methods (indirectly through public method calls)
- ✅ All error scenarios
- ✅ Database transaction handling
- ✅ Business logic validation
- ✅ Integration with dependencies

## Notes
- Tests use PHPUnit's mocking framework for dependency isolation
- Each test focuses on a single scenario for clear failure diagnosis
- Mock objects are configured to return predictable values for consistent testing
- Exception scenarios are thoroughly tested with specific message validation
