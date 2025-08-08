# Agent Location Archive System

## Overview

The Agent Location Archive System automatically creates trajectory archives when an agent completes a task. This system is designed to preserve location history for completed tasks while maintaining efficient data storage.

## How it Works

### Automatic Archive Creation

When an agent records a location with the reason `end_task`, the system automatically:

1. **Checks for existing archive**: Verifies if an archive already exists for the task to avoid duplicates
2. **Collects raw locations**: Gathers all raw location data recorded during the task
3. **Creates trajectory**: Converts the point locations into a LineString geometry representing the agent's path
4. **Calculates statistics**: Computes path length, average speed, and other trajectory metrics
5. **Stores archive**: Persists the archive to the `agent_locations_archive` table

### Archive Data Structure

Each archive contains:
- **Agent and Task references**: Links to the agent and completed task
- **Trajectory geometry**: LineString representing the complete path taken
- **Time bounds**: Start and end timestamps of the trajectory
- **Statistics**:
  - Point count: Number of raw location points
  - Path length: Total distance traveled (in meters)
  - Average speed: Calculated from GPS speed readings or distance/time

### Integration Points

#### 1. AgentLocationService
The main location recording service now includes archive creation logic:
```php
// When recording a location with reason 'end_task'
if ($locationData->isSignificant === true && $locationData->reason === 'end_task') {
    $this->createTaskArchiveOnEnd($agent, $task);
}
```

#### 2. AgentLocationArchiveService
Dedicated service for archive operations:
- `createTaskArchive(Agents $agent, Tasks $task)`: Creates archive from raw locations
- `archiveExistsForTask(Tasks $task)`: Checks if archive already exists
- Internal methods for geometry processing and statistics calculation

## API Usage

### Recording an End Task Location

```bash
POST /api/agent/{encrypted_agent_id}/locations
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "longitude": 2.3522,
  "latitude": 48.8566,
  "accuracy": 10.0,
  "speed": 5.0,
  "batteryLevel": 85.0,
  "isSignificant": true,
  "reason": "end_task",
  "taskId": "encrypted_task_123"
}
```

When this request is processed, the system will:
1. Record the location as usual
2. Automatically create an archive if none exists for the task

## Database Schema

### agent_locations_archive table
- `id`: Primary key
- `agent_id`: Foreign key to agents table
- `task_id`: Foreign key to tasks table
- `geom`: LineString geometry (SRID 4326)
- `start_time`: Timestamp of first location
- `end_time`: Timestamp of last location
- `point_count`: Number of raw location points
- `avg_speed`: Average speed (m/s)
- `path_length`: Total path length (meters)

## Error Handling

The archive creation process is designed to be fault-tolerant:
- Archive creation failures don't prevent location recording
- Duplicate archive creation is prevented
- Errors are logged but don't interrupt the main flow

## Performance Considerations

- Archives are created asynchronously after location recording
- LineString geometries are efficiently stored using PostGIS
- Archive creation only occurs for `end_task` events
- Existing archive check prevents unnecessary processing

## Testing

The system includes comprehensive tests:
- Unit tests for `AgentLocationArchiveService`
- Integration tests for the full flow
- Mock-based testing for database operations

## Future Enhancements

Potential improvements:
- Background job processing for archive creation
- Archive compression for very long trajectories
- Real-time trajectory visualization
- Archive export functionality
