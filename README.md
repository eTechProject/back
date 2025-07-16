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

### 1. Récupérer la branche d’authentification :

```bash
git pull origin feature/authentification

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



