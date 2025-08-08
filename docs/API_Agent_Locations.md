# Agent Location Recording API

## Endpoint: POST /api/agent/{idcryptagent}/locations

This endpoint allows agents to record their current location position.

### Request

**URL Parameters:**
- `idcryptagent` (string, required): The encrypted agent ID

**Headers:**
- `Content-Type: application/json`
- `Authorization: Bearer {token}` (requires ROLE_AGENT)

**Request Body:**
```json
{
  "longitude": 2.3522,
  "latitude": 48.8566,
  "accuracy": 10.0,
  "speed": 5.0,
  "batteryLevel": 85.0,
  "isSignificant": false,
  "reason": null,
  "taskId": "encrypted_task_123"
}
```

**Field Descriptions:**
- `longitude` (float, required): GPS longitude (-180 to 180)
- `latitude` (float, required): GPS latitude (-90 to 90)
- `accuracy` (float, required): GPS accuracy in meters (0 to 1000)
- `speed` (float, optional): Speed in m/s (0 to 55.56 = 200km/h)
- `batteryLevel` (float, optional): Battery percentage (0 to 100)
- `isSignificant` (boolean, optional): Whether this location is significant
- `reason` (string, optional): Reason for significant location. Required if `isSignificant` is true.
  - Valid values: `start_task`, `end_task`, `zone_entry`, `zone_exit`, `manual_report`, `anomaly`, `long_stop`, `out_of_zone`
- `taskId` (string, required): Encrypted task ID

### Response

**Success (201 Created):**
```json
{
  "status": "success",
  "message": "Position enregistrée avec succès",
  "data": {
    "locationId": 123,
    "recordedAt": "2025-08-07T14:30:00+00:00",
    "isSignificant": false,
    "coordinates": {
      "longitude": 2.3522,
      "latitude": 48.8566
    },
    "accuracy": 10.0,
    "speed": 5.0,
    "batteryLevel": 85.0,
    "reason": null
  },
  "timestamp": "2025-08-07T14:30:00+00:00"
}
```

**Validation Error (400 Bad Request):**
```json
{
  "status": "error",
  "message": "Données invalides",
  "errors": {
    "longitude": "La longitude est requise",
    "latitude": "La latitude doit être comprise entre -90 et 90"
  },
  "timestamp": "2025-08-07T14:30:00+00:00"
}
```

**Credibility Error (400 Bad Request):**
```json
{
  "status": "error",
  "message": "Données de localisation non crédibles",
  "errors": ["Les données fournies ne semblent pas crédibles"],
  "timestamp": "2025-08-07T14:30:00+00:00"
}
```

**Business Logic Error (400 Bad Request):**
```json
{
  "status": "error",
  "message": "Agent non trouvé",
  "timestamp": "2025-08-07T14:30:00+00:00"
}
```

### Behavior

1. **Data Validation**: The endpoint validates all input data for format and credibility
2. **Raw Location Storage**: All valid locations are stored in `agent_locations_raw` table
3. **Significant Location Storage**: If `isSignificant` is true, the location is also stored in `agent_locations_significant` table with the provided reason
4. **Real-time Publishing**: Location updates are published via Mercure to `/agents/{id}/location` topic
5. **Task Association**: Locations are linked to the specified task, which must belong to the agent and be active

### Security

- Requires authentication with ROLE_AGENT
- Agent can only record locations for their own tasks
- Task ID must be valid and belong to the authenticated agent
- Task must be in PENDING or IN_PROGRESS status

### Real-time Updates

Published to Mercure topic: `/agents/{agent_id}/location`

**Payload:**
```json
{
  "agent_id": "encrypted_agent_id",
  "longitude": 2.3522,
  "latitude": 48.8566,
  "accuracy": 10.0,
  "speed": 5.0,
  "battery_level": 85.0,
  "recorded_at": "2025-08-07T14:30:00+00:00",
  "is_significant": false,
  "reason": null
}
```
