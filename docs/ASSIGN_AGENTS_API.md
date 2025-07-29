# Test Example for Agent Assignment API

## Prerequisites
- Ensure you have a valid JWT token for authentication
- Have access to encrypted service order ID and agent IDs
- PostGIS extension must be enabled in the database

## Example Usage

### 1. Get Available Agents
```bash
curl -X GET "http://localhost:8000/api/client/available-agents" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

### 2. Assign Agents to Task (Multiple agents with individual coordinates)
```bash
curl -X POST "http://localhost:8000/api/client/assign-agents" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "orderId": "your_encrypted_order_id",
    "agentAssignments": [
      {
        "agentId": "encrypted_agent_id_1",
        "coordinates": [2.3522, 48.8566]
      },
      {
        "agentId": "encrypted_agent_id_2", 
        "coordinates": [2.3542, 48.8576]
      },
      {
        "agentId": "encrypted_agent_id_3",
        "coordinates": [2.3500, 48.8550]
      }
    ]
  }'
```

### 3. Single Agent Assignment
```bash
curl -X POST "http://localhost:8000/api/client/assign-agents" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "orderId": "your_encrypted_order_id",
    "agentAssignments": [
      {
        "agentId": "encrypted_agent_id_1",
        "coordinates": [2.3522, 48.8566]
      }
    ]
  }'
```

## Request Validation
The API validates:
- `orderId`: Must be a valid encrypted service order ID
- `agentAssignments`: Array of agent assignment objects (minimum 1 assignment)
  - Each assignment must contain:
    - `agentId`: Valid encrypted agent ID
    - `coordinates`: Array with exactly 2 numeric values [longitude, latitude]

## Business Logic
- Each agent gets assigned to their specific coordinates
- Agents must be available (no pending or in-progress tasks)
- Service order must exist and be valid
- Creates individual Task entities for each agent with their coordinates
- All operations are wrapped in a database transaction

## Error Handling
- 400: Invalid request data or validation errors
- 500: Server errors (agent not found, database issues, etc.)
