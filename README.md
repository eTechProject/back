# 🔐 API Symfony - ### 1.Cloner le projet 
git clone https://github.com/eTechProject/back.git

cd back

### Récupérer la branche souhaitée :
```bash
git pull origin feature/authentification
```cation (JWT)

Ce projet est une **API construite avec Symfony 7.3** intégrant un système d’authentification via **JWT**, et entièrement conteneurisée avec **Docker**.  
Le `Makefile` inclus permet d’automatiser les commandes courantes.

---

## 📦 Prérequis

Avant de démarrer, assurez-vous d’avoir les outils suivants installés sur votre machine :

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- [Git](https://git-scm.com/)
- (Facultatif) Symfony CLI pour le debug local

---

## 🚀 Lancer le projet
### 1.Cloner le projet 
git clone https://github.com/eTechProject/back.git

<<<<<<< HEAD
cd back
=======
###Récupérer la branche d’authentification :
```bash
git pull origin feature/authentification
>>>>>>> 429cac1 (Update README.md)

### 2. Créer le fichier .env dans la racine du projet puis lance
composer install

### 3-lancer le conteneur docker
'''bash

make build

make up

### 4-faire la migration vers la base de données

make bash

php bin/console doctrine:database:create

php bin/console make:migration

php bin/console doctrine:migration:migrate

### 5-genère les clés JWT

Créer le dossier s’il n’existe pas
mkdir -p config/jwt

Générer la clé privée (avec passphrase)
openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:your_passphrase

Générer la clé publique
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:your_passphrase

---

## ☁️ Déploiement sur Render

This project includes a `render.yaml` blueprint and a production-ready `Dockerfile.render` to deploy on [Render](https://render.com) using Docker.

### 1. Activer le blueprint
1. Pousser la branche contenant `render.yaml` sur GitHub.
2. Dans Render: New + Blueprint > fournir l'URL du repo > sélectionner la branche.
3. Render provisionnera:
   - Un service web `guard-api` (Docker `Dockerfile.render`).
   - Une base Postgres `guard-db` (connexion injectée dans `DATABASE_URL`).

### 2. Variables d'environnement à définir
Dans le dashboard Render (ou via CLI), définir / vérifier :

| Variable | Description |
|----------|-------------|
| `APP_ENV` | `prod` |
| `APP_SECRET` | Auto-générée (peut être régénérée) |
| `DATABASE_URL` | Injectée automatiquement depuis la base | 
| `JWT_SECRET_KEY` | `/var/www/app/config/jwt/private.pem` |
| `JWT_PUBLIC_KEY` | `/var/www/app/config/jwt/public.pem` |
| `JWT_PASSPHRASE` | Votre passphrase openssl (ne pas committer) |
| `MERCURE_URL` | URL du hub Mercure (si activé) |
| `MERCURE_JWT_TOKEN` | Token d'accès (si nécessaire) |
| `RUN_MIGRATIONS` | `1` pour exécuter les migrations au démarrage |

### 3. Clés JWT
Les clés présentes dans `config/jwt` sont utilisées via chemins. Remplacez-les si nécessaire et mettez à jour `JWT_PASSPHRASE`.

### 4. Migrations
Le script d'entrypoint exécute automatiquement `doctrine:migrations:migrate` (ignorera si la DB n'est pas prête et réessaiera au prochain déploiement). Pour désactiver : définir `RUN_MIGRATIONS=0`.

### 5. Mercure (optionnel)
Dé-commentez la section Mercure dans `render.yaml`, déployez, puis mettez à jour `MERCURE_URL` dans le service API (format: `https://<nom-du-service>.onrender.com/.well-known/mercure`). Fournissez les clés JWT correspondantes.

### 6. Déploiements
Chaque push sur la branche déclenche un build. Vous pouvez activer/désactiver l'auto-deploy dans le dashboard.

### 7. Healthcheck
Le healthcheck pointe sur `/index.php` (modifiable dans `render.yaml`).

### 8. Logs & Debug
Consultez les logs de build et runtime dans Render > Service > Logs. Si la page reste en attente, vérifier que le container écoute bien sur le port `$PORT` (généré) – géré via `nginx.conf.template`.

### 9. Commandes manuelles
Pour exécuter une commande console sur Render: Service > Shell > `php bin/console <commande>`.

---
### Résumé des fichiers ajoutés
| Fichier | Rôle |
|---------|------|
| `Dockerfile.render` | Image production (php-fpm + nginx + supervisor) |
| `entrypoint.sh` | Prépare Nginx, exécute les migrations, lance Supervisor |
| `docker/prod/nginx.conf.template` | Template Nginx avec substitution du port |
| `docker/prod/supervisord.conf` | Lance php-fpm et nginx simultanément |
| `render.yaml` | Blueprint Render (service + base de données) |

---
Pour ajustements supplémentaires (cache warmup, assets, workers Messenger), ouvrir une issue ou étendre `Dockerfile.render`.



