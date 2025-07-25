# 🔐 API Symfony - Authentification (JWT)

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

cd back

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

###genère les clés JWT
Créer le dossier s’il n’existe pas
mkdir -p config/jwt

Générer la clé privée (avec passphrase)
openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:your_passphrase

Générer la clé publique
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:your_passphrase



