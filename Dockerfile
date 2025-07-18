FROM php:8.2-fpm

RUN echo "deb https://deb.debian.org/debian bookworm main" > /etc/apt/sources.list && \
    echo "deb https://deb.debian.org/debian-security bookworm-security main" >> /etc/apt/sources.list && \
    echo "deb https://deb.debian.org/debian bookworm-updates main" >> /etc/apt/sources.list && \
    apt-get update && \
    apt-get install -y git unzip zip libpq-dev curl && \
    docker-php-ext-install pdo pdo_pgsql
