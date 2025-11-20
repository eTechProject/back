# Map Reload on Mission Notifications

## Overview
When an agent receives a notification about mission assignment or cancellation, the map will automatically reload to show the updated mission positions.

## Implementation

### Backend Changes

#### 1. NotificationResponseDTO
Added `reloadMap` field to trigger map reload on frontend:
```php
public readonly bool $reloadMap = false
```

#### 2. NotificationService
Updated `createNotification()` method to accept `reloadMap` parameter:
```php
public function createNotification(
    string $titre,
    string $message,
    NotificationType $type = NotificationType::INFO,
    NotificationTarget $cible = NotificationTarget::ALL,
    ?User $user = null,
    bool $reloadMap = false // NEW PARAMETER
): Notification
```

#### 3. NotificationPublisher
- Updated `publishNotification()` to accept and pass `reloadMap` flag
- Updated `convertToDTO()` to include `reloadMap` in the DTO

#### 4. TaskService
Both notification calls now trigger map reload:

**Mission Assignment:**
```php
$this->notificationService->createNotification(
    "Nouvelle Mission Assignée",
    "Vous avez été assigné à une nouvelle mission...",
    NotificationType::ASSIGNMENT,
    NotificationTarget::AGENT,
    $assignment['agent']->getUser(),
    true // reloadMap: trigger map reload on frontend
);
```

**Mission Cancellation:**
```php
$this->notificationService->createNotification(
    "Mission Annulée",
    "La mission à la position {$positionText}...",
    NotificationType::ASSIGNMENT,
    NotificationTarget::AGENT,
    $task->getAgent()->getUser(),
    true // reloadMap: trigger map reload on frontend
);
```

## Frontend Integration

When the agent receives a notification via Mercure, check the `reloadMap` flag:

```javascript
// Example frontend code
mercureEventSource.addEventListener('message', (event) => {
    const notification = JSON.parse(event.data);
    
    if (notification.type === 'notification') {
        const data = notification.data;
        
        // Show notification to user
        showNotification(data.titre, data.message);
        
        // Reload map if needed
        if (data.reloadMap === true) {
            reloadMapContent(); // Refresh map markers/layers
        }
    }
});
```

## Notification JSON Structure

```json
{
    "type": "notification",
    "data": {
        "id": "encrypted_notification_id",
        "titre": "Nouvelle Mission Assignée",
        "message": "Vous avez été assigné à une nouvelle mission à la position (-18.873885, 47.518165)...",
        "type": "assignment",
        "cible": "agent",
        "isRead": false,
        "createdAt": "2025-11-20 13:42:57",
        "userId": "encrypted_user_id",
        "reloadMap": true
    }
}
```

## Benefits

✅ Automatic map updates when missions are assigned
✅ Automatic map updates when missions are cancelled
✅ No manual refresh required by the agent
✅ Real-time synchronization between notifications and map state
✅ Better user experience for mobile agents

## Testing

To test the feature:

1. Assign a new mission to an agent
2. Agent should receive notification with `reloadMap: true`
3. Agent's map should automatically reload showing the new mission marker

4. Cancel a mission assigned to an agent
5. Agent should receive notification with `reloadMap: true`
6. Agent's map should automatically reload removing the cancelled mission marker
