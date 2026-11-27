# Imagem do app: PHP + FrankenPHP + Composer. Nada é instalado na máquina (RNF-09).
FROM dunglas/frankenphp:1-php8.5

RUN install-php-extensions pdo_sqlite intl zip gd bcmath pcntl \
    && apt-get update && apt-get install -y --no-install-recommends sqlite3 unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Caddy/FrankenPHP grava config e dados aqui; o container roda com o UID do usuário.
ENV XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data \
    COMPOSER_HOME=/tmp/composer \
    SERVER_NAME=":8000"

WORKDIR /app
EXPOSE 8000
