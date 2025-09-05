# API Endpoint: Historique des paiements généraux pour un client

## Endpoint
```
GET /api/client/{id}/payment-history
```

## Description
Récupère l'historique complet des paiements d'un client (tous providers: Stripe, Cybersource, etc.) basé sur l'entité PaymentHistory. Inclut la pagination, les filtres et les factures pour les paiements Stripe.

## Paramètres

### Path Parameters
- `id` (string, requis) : ID chiffré du client

### Query Parameters
- `page` (int, optionnel) : Numéro de page (défaut: 1)
- `limit` (int, optionnel) : Nombre d'éléments par page (défaut: 10, max: 50)
- `status` (string, optionnel) : Filtre par statut (`pending`, `completed`, `failed`, `cancelled`, `refunded`)
- `provider` (string, optionnel) : Filtre par provider (`stripe`, `cybersource`, `paypal`)
- `start_date` (string, optionnel) : Date de début (format: YYYY-MM-DD)
- `end_date` (string, optionnel) : Date de fin (format: YYYY-MM-DD)

## Authentification
- **Rôle requis** : `ROLE_CLIENT`
- **Headers** : `Authorization: Bearer <token>`

## Réponses

### Succès (200)
```json
{
    "status": "success",
    "data": {
        "history": [
            {
                "id": "eWZLY1JMb2RnK2hjV2hHNjMwc0NXdz09",
                "payment_id": "cWM0OVVsWFp6bkxRMVZyMGZZbk1YUT09",
                "amount": 179.99,
                "currency": "EUR",
                "status": "success",
                "provider": "stripe",
                "description": "Pack Premium - Test Postman",
                "date": "2025-09-03 09:00:50",
                "created_at": "2025-09-03 09:00:50",
                "stripe_payment_id": "ch_3S3CYf9KyBGiInQM06qdqJFZ",
                "provider_response": null,
                "pack_info": {
                    "id": "Rkt6MUdEUG5CZ2d6dUZxVXRvUStLQT09",
                    "name": "Pack Premium - Test Postman",
                    "price": 999.99,
                    "nb_agents": 5
                },
                "invoice": {
                    "invoice_number": "STRIPE-06QDQJFZ",
                    "invoice_date": "2025-09-03",
                    "due_date": "2025-09-03",
                    "amount": 179.99,
                    "currency": "EUR",
                    "status": "success",
                    "customer": {
                        "email": "client@example.com",
                        "name": "John Doe"
                    },
                    "items": [
                        {
                            "description": "Pack Premium - Test Postman",
                            "quantity": 1,
                            "unit_price": 179.99,
                            "total_price": 179.99
                        }
                    ],
                    "subtotal": 179.99,
                    "tax_rate": 0.2,
                    "tax_amount": 35.998000000000005,
                    "total": 215.988,
                    "payment_method": "Stripe",
                    "transaction_id": "ch_3S3CYf9KyBGiInQM06qdqJFZ",
                    "receipt_url": "https://pay.stripe.com/receipts/payment/CAcaFwoVYWNjdF8xUzJXRHU5S3lCR2lJblFNKNiD4cUGMgZHE-mKOQE6LBZwhs0nKtofZfEMOfnK-0CZpApPs2MONRuAeHQcLajPFi--vDtPbNn5A9LK"
                }
            },
            {
                "id": "M1lscmtxZnB6bUErMjFXcjZGam5BQT09",
                "payment_id": "cWM0OVVsWFp6bkxRMVZyMGZZbk1YUT09",
                "amount": 179.99,
                "currency": "EUR",
                "status": "success",
                "provider": "stripe",
                "description": "Pack Premium - Test Postman",
                "date": "2025-09-03 08:48:20",
                "created_at": "2025-09-03 08:48:20",
                "stripe_payment_id": "ch_3S3CMZ9KyBGiInQM1WCkfnzh",
                "provider_response": null,
                "pack_info": {
                    "id": "Rkt6MUdEUG5CZ2d6dUZxVXRvUStLQT09",
                    "name": "Pack Premium - Test Postman",
                    "price": 999.99,
                    "nb_agents": 5
                },
                "invoice": {
                    "invoice_number": "STRIPE-1WCKFNZH",
                    "invoice_date": "2025-09-03",
                    "due_date": "2025-09-03",
                    "amount": 179.99,
                    "currency": "EUR",
                    "status": "success",
                    "customer": {
                        "email": "client@example.com",
                        "name": "John Doe"
                    },
                    "items": [
                        {
                            "description": "Pack Premium - Test Postman",
                            "quantity": 1,
                            "unit_price": 179.99,
                            "total_price": 179.99
                        }
                    ],
                    "subtotal": 179.99,
                    "tax_rate": 0.2,
                    "tax_amount": 35.998000000000005,
                    "total": 215.988,
                    "payment_method": "Stripe",
                    "transaction_id": "ch_3S3CMZ9KyBGiInQM1WCkfnzh",
                    "receipt_url": "https://pay.stripe.com/receipts/payment/CAcaFwoVYWNjdF8xUzJXRHU5S3lCR2lJblFNKNiD4cUGMgZ8a6qR7Vs6LBbtgbc7t7DYNz9d2wdXsXm1202z695Yw8qaOEawr0QV0EvNTQb4QIbBkMuT"
                }
            },
            {
                "id": "dGZmN3hjQUxaNEpML0tSVm5kWFhqZz09",
                "payment_id": "cWM0OVVsWFp6bkxRMVZyMGZZbk1YUT09",
                "amount": 179.99,
                "currency": "EUR",
                "status": "success",
                "provider": "stripe",
                "description": "Pack Premium - Test Postman",
                "date": "2025-09-03 08:06:00",
                "created_at": "2025-09-03 08:06:00",
                "stripe_payment_id": "ch_3S3Bhb9KyBGiInQM0bVlqqqU",
                "provider_response": null,
                "pack_info": {
                    "id": "Rkt6MUdEUG5CZ2d6dUZxVXRvUStLQT09",
                    "name": "Pack Premium - Test Postman",
                    "price": 999.99,
                    "nb_agents": 5
                },
                "invoice": {
                    "invoice_number": "STRIPE-0BVLQQQU",
                    "invoice_date": "2025-09-03",
                    "due_date": "2025-09-03",
                    "amount": 179.99,
                    "currency": "EUR",
                    "status": "success",
                    "customer": {
                        "email": "client@example.com",
                        "name": "John Doe"
                    },
                    "items": [
                        {
                            "description": "Pack Premium - Test Postman",
                            "quantity": 1,
                            "unit_price": 179.99,
                            "total_price": 179.99
                        }
                    ],
                    "subtotal": 179.99,
                    "tax_rate": 0.2,
                    "tax_amount": 35.998000000000005,
                    "total": 215.988,
                    "payment_method": "Stripe",
                    "transaction_id": "ch_3S3Bhb9KyBGiInQM0bVlqqqU",
                    "receipt_url": "https://pay.stripe.com/receipts/payment/CAcaFwoVYWNjdF8xUzJXRHU5S3lCR2lJblFNKNmD4cUGMgbqrgjBCpM6LBZhaNsvyD633NpPTcWipwMPVleAvXzaddn2uHV61DHlPWOeu5Kf43cM6_y-"
                }
            }
        ],
        "pagination": {
            "page": 1,
            "limit": 10,
            "total": 25,
            "total_pages": 3,
            "has_next": true,
            "has_prev": false
        },
        "filters": {
            "status": null,
            "provider": "stripe",
            "start_date": null,
            "end_date": null
        }
    }
}
```

### Erreurs

#### 400 - Paramètres invalides
```json
{
    "status": "error",
    "message": "Statut invalide. Valeurs autorisées: pending, completed, failed, cancelled, refunded"
}
```

#### 403 - Accès refusé
```json
{
    "status": "error",
    "message": "Accès refusé"
}
```

## Sécurité
- Seuls les paiements du client authentifié sont retournés
- Les IDs sont chiffrés pour la sécurité
- Vérification de propriété des données

## Fonctionnalités
- **Pagination** : Navigation par pages avec métadonnées
- **Filtres multiples** : Par statut, provider, dates
- **Factures automatiques** : Génération de factures pour les paiements Stripe
- **Multi-provider** : Support de Stripe, Cybersource, PayPal
- **IDs chiffrés** : Sécurisation des identifiants

## Exemples d'utilisation

### Récupération simple
```bash
curl -X GET \
  "https://api.example.com/api/client/ZW5jcnlwdGVkQ2xpZW50SWQxMjM=/payment-history" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Avec filtres et pagination
```bash
curl -X GET \
  "https://api.example.com/api/client/ZW5jcnlwdGVkQ2xpZW50SWQxMjM=/payment-history?page=2&limit=5&provider=stripe&status=completed" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### JavaScript
```javascript
const response = await fetch('/api/client/ZW5jcnlwdGVkQ2xpZW50SWQxMjM=/payment-history?provider=stripe', {
    method: 'GET',
    headers: {
        'Authorization': `Bearer ${jwtToken}`,
        'Content-Type': 'application/json'
    }
});

const data = await response.json();
console.log(data.data.history); // Affiche l'historique avec factures
```

## Notes importantes
- L'endpoint remplace l'ancien `/api/client/{id}/stripe/payment-history`
- Les factures ne sont générées que pour les paiements Stripe
- Les IDs chiffrés garantissent la sécurité des données
- La pagination optimise les performances pour de gros volumes
- **URL de reçu Stripe** : L'URL de reçu officielle est récupérée automatiquement depuis Stripe pour chaque paiement
- Les URLs de reçu permettent aux clients d'accéder directement à leurs factures Stripe officielles
