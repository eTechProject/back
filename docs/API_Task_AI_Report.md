# API Endpoint: Génération de rapport IA pour une tâche

## Endpoint
```
POST /api/client/task/{encryptedTaskId}/generate-report
```

## Description
Génère un rapport détaillé de mission de sécurité en utilisant l'intelligence artificielle Google Gemini. Ce rapport compile automatiquement toutes les données de la mission (informations de la tâche, déplacements de l'agent, communications échangées) pour produire un document professionnel en français.

## Paramètres

### URL Parameters
- `encryptedTaskId` (string, required): ID crypté de la tâche pour laquelle générer le rapport

## Authentification
- **Rôle requis** : `ROLE_CLIENT`
- **Headers** : `Authorization: Bearer <token>`

## Réponses

### Succès (200)
```json
{
    "status": "success",
    "message": "Rapport généré avec succès",
    "data": {
        "report": "# RAPPORT DE MISSION DE SÉCURITÉ\n\n## Contexte de la mission\n\nMission de surveillance effectuée le 01/01/2025...",
        "task_id": 123,
        "generated_at": "2025-01-01T19:30:00+00:00"
    }
}
```

### Erreurs

#### 400 - Tâche non terminée
```json
{
    "status": "error",
    "message": "Le rapport ne peut être généré que pour les tâches terminées"
}
```

#### 403 - Accès refusé
```json
{
    "status": "error",
    "message": "Accès non autorisé à cette tâche"
}
```

#### 404 - Tâche non trouvée
```json
{
    "status": "error",
    "message": "Tâche non trouvée"
}
```

#### 500 - Erreur de génération
```json
{
    "status": "error",
    "message": "Erreur lors de la génération du rapport: [détails de l'erreur]"
}
```

## Sécurité
- L'utilisateur authentifié doit être le client propriétaire de la tâche
- Seules les tâches avec le statut `COMPLETED` peuvent générer un rapport
- L'ID de la tâche doit être crypté selon le système de cryptage de l'application

## Fonctionnalités

### Données incluses dans le rapport
1. **Informations de la mission** :
   - ID, description, type de mission
   - Position assignée (coordonnées GPS)
   - Dates de début et fin
   - Statut de la mission

2. **Informations du client** :
   - Nom, email, téléphone

3. **Informations de l'agent** :
   - Nom, sexe, adresse, téléphone
   - Photo de profil

4. **Déplacements de l'agent** :
   - Distance totale parcourue (en mètres)
   - Vitesse moyenne (en km/h)
   - Durée de présence
   - Nombre de points GPS enregistrés

5. **Communications** :
   - Analyse et synthèse de tous les messages échangés
   - Instructions du client
   - Réponses et confirmations de l'agent
   - Incidents ou difficultés signalés

### Structure du rapport généré
Le rapport IA est structuré avec les sections suivantes :
- **Contexte de la mission**
- **Informations sur l'agent**
- **Informations sur le client**
- **Déroulement de la mission** (déplacements et activité)
- **Synthèse des communications**
- **Conclusion et recommandations**

## Exemples d'utilisation

### Génération simple
```bash
curl -X POST \
  "https://api.example.com/api/client/task/ZW5jcnlwdGVkVGFza0lkMTIz/generate-report" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

### Avec gestion d'erreur
```javascript
try {
    const response = await fetch('/api/client/task/encrypted_task_123/generate-report', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + jwtToken,
            'Content-Type': 'application/json'
        }
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
        console.log('Rapport généré:', result.data.report);
    } else {
        console.error('Erreur:', result.message);
    }
} catch (error) {
    console.error('Erreur de réseau:', error);
}
```

## Notes importantes
- La génération du rapport nécessite une connexion internet pour accéder à l'API Google Gemini
- Le processus peut prendre quelques secondes selon la quantité de données à analyser
- Les rapports sont générés en temps réel et ne sont pas stockés en base de données
- L'API utilise Google Gemini 2.0 Flash pour des performances optimales
- Toutes les dates et coordonnées sont formatées selon les standards français
- Le rapport est entièrement généré en français avec un style professionnel

## Configuration technique
- **Service IA** : Google Gemini 2.0 Flash
- **Endpoint IA** : `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent`
- **Authentification IA** : Clé API Google (variable d'environnement `GOOGLE_AI_API_KEY`)
- **Format de réponse** : Texte Markdown structuré
