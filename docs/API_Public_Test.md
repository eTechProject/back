# API Publique de Test - eTech

Cette documentation décrit les endpoints publics disponibles pour tester votre application déployée.

## Base URL
```
https://votre-domaine.com/api/public
```

## Endpoints Disponibles

### 1. Health Check
**GET** `/api/public/health`

Vérification de l'état de santé de l'API.

**Réponse:**
```json
{
    "status": "success",
    "message": "API is healthy and running",
    "timestamp": "2025-08-19T10:30:00+00:00",
    "version": "1.0.0",
    "environment": "prod"
}
```

### 2. Informations API
**GET** `/api/public/info`

Informations générales sur l'API et les endpoints disponibles.

**Réponse:**
```json
{
    "status": "success",
    "data": {
        "api_name": "eTech Agent Location API",
        "version": "1.0.0",
        "description": "API pour la gestion des agents et des localisations",
        "environment": "prod",
        "php_version": "8.2.x",
        "framework": "Symfony 6.x",
        "available_endpoints": {
            "health": "GET /api/public/health - Vérification de la santé de l'API",
            "info": "GET /api/public/info - Informations sur l'API",
            "test": "GET /api/public/test - Endpoint de test simple",
            "echo": "POST /api/public/echo - Echo des données envoyées"
        }
    },
    "timestamp": "2025-08-19T10:30:00+00:00"
}
```

### 3. Test Simple
**GET** `/api/public/test`

Endpoint de test qui retourne des données d'exemple.

**Réponse:**
```json
{
    "status": "success",
    "message": "Test endpoint is working correctly",
    "data": {
        "server_time": "2025-08-19T10:30:00+00:00",
        "random_number": 742,
        "test_data": {
            "sample_agent": {
                "id": "encrypted_agent_123",
                "name": "John Doe",
                "status": "active"
            },
            "sample_location": {
                "longitude": 2.3522,
                "latitude": 48.8566,
                "accuracy": 10.0,
                "timestamp": "2025-08-19T10:30:00+00:00"
            }
        }
    }
}
```

### 4. Echo
**POST** `/api/public/echo`

Endpoint qui renvoie les données que vous lui envoyez (utile pour tester les requêtes POST).

**Headers requis:**
```
Content-Type: application/json
```

**Exemple de requête:**
```json
{
    "test": "Hello World",
    "data": {
        "key": "value"
    }
}
```

**Réponse:**
```json
{
    "status": "success",
    "message": "Echo endpoint - your data returned",
    "received_data": {
        "test": "Hello World",
        "data": {
            "key": "value"
        }
    },
    "raw_content": "{\"test\":\"Hello World\",\"data\":{\"key\":\"value\"}}",
    "headers": {
        "content_type": "application/json",
        "user_agent": "curl/7.68.0",
        "accept": "*/*"
    },
    "method": "POST",
    "timestamp": "2025-08-19T10:30:00+00:00"
}
```

## Exemples d'utilisation

### Avec curl:

```bash
# Health check
curl -X GET https://votre-domaine.com/api/public/health

# Info API
curl -X GET https://votre-domaine.com/api/public/info

# Test simple
curl -X GET https://votre-domaine.com/api/public/test

# Echo POST
curl -X POST https://votre-domaine.com/api/public/echo \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello from API test!"}'
```

### Avec JavaScript (fetch):

```javascript
// Health check
fetch('https://votre-domaine.com/api/public/health')
  .then(response => response.json())
  .then(data => console.log(data));

// Echo POST
fetch('https://votre-domaine.com/api/public/echo', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    message: 'Hello from JavaScript!'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

## Codes de statut

- **200 OK**: Requête réussie
- **404 Not Found**: Endpoint non trouvé
- **405 Method Not Allowed**: Méthode HTTP non autorisée
- **500 Internal Server Error**: Erreur serveur

## Notes importantes

- Ces endpoints ne nécessitent **aucune authentification**
- Ils sont conçus uniquement pour **tester le déploiement** et vérifier que l'API fonctionne
- En production, vous pourriez vouloir désactiver ou restreindre l'accès à certains de ces endpoints
- L'endpoint `/health` est particulièrement utile pour les vérifications de santé automatisées (monitoring, load balancer, etc.)
