# Guide de Déploiement sur Render avec Mercure

## Configuration des Variables d'Environnement sur Render

Dans votre dashboard Render, définissez les variables d'environnement suivantes :

### Variables Obligatoires
```
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL=[Render PostgreSQL Connection String]
MERCURE_URL=http://127.0.0.1:3000/.well-known/mercure
MERCURE_PUBLIC_URL=https://[VOTRE-SERVICE-NAME].onrender.com/.well-known/mercure
MERCURE_JWT_SECRET=[Générez une clé secrète forte d'au moins 32 caractères]
MERCURE_JWT_PUBLISHER=true
JWT_PASSPHRASE=[Générez une phrase secrète forte pour JWT]
CORS_ORIGINS=*
PORT=10000
```

### Remplacer [VOTRE-SERVICE-NAME]
Remplacez `[VOTRE-SERVICE-NAME]` par le nom réel de votre service Render dans la variable `MERCURE_PUBLIC_URL`.

### Génération des clés secrètes
Vous pouvez générer des clés secrètes avec :
```bash
# Pour MERCURE_JWT_SECRET (au moins 32 caractères)
openssl rand -hex 32

# Pour JWT_PASSPHRASE
openssl rand -base64 32
```

## Configuration PostgreSQL sur Render

1. Créez une base de données PostgreSQL sur Render
2. Activez l'extension PostGIS si nécessaire
3. Copiez la chaîne de connexion dans `DATABASE_URL`

## Déploiement

### Option 1: Utiliser render.yaml (Recommandé)
1. Pushez le code avec le fichier `render.yaml` sur votre repository
2. Connectez votre repository à Render
3. Render détectera automatiquement la configuration

### Option 2: Configuration manuelle
1. Créez un nouveau Web Service sur Render
2. Connectez votre repository Git
3. Configurez :
   - **Environment**: Docker
   - **Dockerfile Path**: `./Dockerfile`
   - **Port**: 10000
   - **Health Check Path**: `/health`
4. Ajoutez toutes les variables d'environnement listées ci-dessus

## Vérification du Déploiement

### Endpoints de test publics (aucune auth requise):
- `GET https://[VOTRE-SERVICE].onrender.com/health` - Health check
- `GET https://[VOTRE-SERVICE].onrender.com/api/public/health` - API health
- `GET https://[VOTRE-SERVICE].onrender.com/api/public/info` - API info

### Mercure:
- `GET https://[VOTRE-SERVICE].onrender.com/.well-known/mercure` - Mercure hub
- Test avec curl:
```bash
curl -N -H "Accept: text/event-stream" "https://[VOTRE-SERVICE].onrender.com/.well-known/mercure?topic=test"
```

## Structure des Services

Votre application déploiera :
- **Port 10000**: API Symfony (exposé publiquement)
- **Port 3000**: Serveur Mercure (interne, accessible via proxy Nginx)
- **Nginx**: Proxy qui route `/api/*` vers Symfony et `/.well-known/mercure` vers Mercure

## Troubleshooting

### Logs
Consultez les logs de Render pour diagnostiquer les problèmes :
- Build logs pour les erreurs de construction
- Deploy logs pour les erreurs de démarrage
- Service logs pour les erreurs d'exécution

### Erreurs Communes
1. **Database connection failed**: Vérifiez `DATABASE_URL`
2. **Mercure not responding**: Vérifiez `MERCURE_JWT_SECRET` et `MERCURE_PUBLIC_URL`
3. **CORS errors**: Ajustez `CORS_ORIGINS` si nécessaire
4. **JWT errors**: Vérifiez `JWT_PASSPHRASE`

### Tests de Connectivité
```bash
# Test health check
curl https://[VOTRE-SERVICE].onrender.com/health

# Test Mercure (doit retourner du texte/événements SSE)
curl -N -H "Accept: text/event-stream" "https://[VOTRE-SERVICE].onrender.com/.well-known/mercure?topic=test"

# Test API
curl https://[VOTRE-SERVICE].onrender.com/api/public/info
```

## Notes Importantes

- Le premier démarrage peut prendre 2-3 minutes (installation des dépendances, génération des clés, migrations)
- Render met les services en veille après inactivité - le premier accès après inactivité sera plus lent
- Les logs sont disponibles en temps réel dans le dashboard Render
- PostGIS est configuré automatiquement au démarrage si la base le supporte
