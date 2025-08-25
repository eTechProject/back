# API Multi-Message

## Endpoint: POST /api/messages/multi

Permet à un client d'envoyer un seul message à plusieurs agents en même temps. Chaque agent reçoit le message comme une conversation distincte.

### Authentification
- Rôle requis: `ROLE_CLIENT`
- Token JWT requis dans l'en-tête Authorization

### Request

**URL:** `POST /api/messages/multi`

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {jwt_token}
```

**Body:**
```json
{
    "sender_id": "encrypted_sender_id",
    "receiver_ids": [
        "encrypted_agent_id_1",
        "encrypted_agent_id_2",
        "encrypted_agent_id_3"
    ],
    "order_id": "encrypted_order_id",
    "content": "Votre message ici"
}
```

**Paramètres:**
- `sender_id` (string, required): ID crypté de l'expéditeur
- `receiver_ids` (array, required): Tableau d'IDs cryptés des destinataires (agents)
- `order_id` (string, required): ID crypté de la commande de service
- `content` (string, required): Contenu du message (1-5000 caractères)

### Response

**Succès (200):**
```json
{
    "status": "success",
    "data": {
        "total_sent": 3,
        "total_failed": 0,
        "successful_conversations": [
            {
                "receiver_id": "encrypted_agent_id_1",
                "message_id": "encrypted_message_id_1"
            },
            {
                "receiver_id": "encrypted_agent_id_2",
                "message_id": "encrypted_message_id_2"
            },
            {
                "receiver_id": "encrypted_agent_id_3",
                "message_id": "encrypted_message_id_3"
            }
        ],
        "failed_conversations": []
    },
    "message": "Message envoyé avec succès à 3 agents"
}
```

**Succès partiel (200):**
```json
{
    "status": "success",
    "data": {
        "total_sent": 2,
        "total_failed": 1,
        "successful_conversations": [
            {
                "receiver_id": "encrypted_agent_id_1",
                "message_id": "encrypted_message_id_1"
            },
            {
                "receiver_id": "encrypted_agent_id_2",
                "message_id": "encrypted_message_id_2"
            }
        ],
        "failed_conversations": [
            {
                "receiver_id": "encrypted_agent_id_3",
                "error": "L'agent destinataire n'est pas assigné à cette commande"
            }
        ]
    },
    "message": "Message envoyé à 2/3 agents (2 succès, 1 échecs)"
}
```

**Erreur de validation (422):**
```json
{
    "status": "error",
    "message": "Données de requête invalides",
    "errors": {
        "receiver_ids": "Au moins un destinataire est requis",
        "content": "Le contenu du message est requis"
    }
}
```

**Erreur d'authentification (401):**
```json
{
    "status": "error",
    "message": "User not authenticated"
}
```

**Erreur de validation métier (400):**
```json
{
    "status": "error",
    "message": "Un ou plusieurs identifiants sont invalides"
}
```

**Erreur serveur (500):**
```json
{
    "status": "error",
    "message": "Une erreur inattendue s'est produite lors de l'envoi des messages"
}
```

### Règles métier

1. **Expéditeur**: Doit être un client ou un agent avec accès à la commande
2. **Destinataires**: Doivent être des agents assignés à la commande ou le client de la commande
3. **Auto-envoi**: Un utilisateur ne peut pas s'envoyer un message à lui-même
4. **Doublons**: Les IDs de destinataires en double sont automatiquement supprimés
5. **Conversations**: Chaque destinataire reçoit le message comme une conversation séparée
6. **Mercure**: Chaque message est publié via Mercure pour la réception en temps réel

### Exemple d'utilisation

```javascript
// Exemple avec fetch
const response = await fetch('/api/messages/multi', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + jwtToken
    },
    body: JSON.stringify({
        sender_id: 'enc_client_123',
        receiver_ids: ['enc_agent_456', 'enc_agent_789', 'enc_agent_012'],
        order_id: 'enc_order_345',
        content: 'Bonjour, j\'aimerais avoir une mise à jour sur le statut de ma commande.'
    })
});

const result = await response.json();
console.log(result);
```

### Notes importantes

- Tous les IDs doivent être cryptés selon la méthode de cryptage utilisée par l'application
- La publication Mercure se fait de manière asynchrone avec retry logic en cas d'échec
- Les échecs individuels n'interrompent pas le traitement des autres destinataires
- Le log des opérations est disponible pour le debugging
