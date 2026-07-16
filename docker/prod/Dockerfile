# Produkční image pro MojiMili CRM (Portainer + Traefik).
# Vše je zabudované: composer závislosti (bez dev), sestavené Vite assety,
# běh přes supervisord (web + queue worker + scheduler).
#
# Pozor: composer.json závisí na privátním repu fondly-cz/ares-connector, proto
# build potřebuje GitHub token. Předává se přes build arg COMPOSER_AUTH a díky
# multi-stage buildu nezůstává ve výsledném image (viz README).

# --- Základ: PHP + Node runtime (sdílený všemi fázemi) --------------------------
FROM ubuntu:24.04 AS base

LABEL maintainer="fondly.cz"

ARG NODE_VERSION=22
ARG PHP_VERSION=8.4

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Europe/Prague
ENV PHP_CLI_SERVER_WORKERS=4

WORKDIR /var/www/html

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg curl ca-certificates zip unzip git supervisor libcap2-bin \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-igbinary \
        php${PHP_VERSION}-readline \
    && setcap "cap_net_bind_service=+ep" /usr/bin/php${PHP_VERSION} \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g pnpm@10 \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# --- Fáze 1: composer závislosti (tady žije GitHub token) -----------------------
FROM base AS vendor

# JSON pro composer, např.: {"github-oauth":{"github.com":"ghp_..."}}
# Alternativně lze použít BuildKit secret (viz README).
ARG COMPOSER_AUTH=""
ENV COMPOSER_AUTH=${COMPOSER_AUTH}

COPY composer.json composer.lock ./
RUN --mount=type=secret,id=composer_auth,target=/root/.config/composer/auth.json,required=false \
    composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

# --- Fáze 2: build frontendu (Vite importuje ZiggyVue z vendor/) -----------------
FROM base AS assets

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor

# Dočasný .env (z .env.example) jen kvůli VITE_APP_NAME při buildu.
RUN cp .env.example .env \
    && pnpm run build \
    && rm -f .env

# --- Fáze 3: výsledný image (bez tokenu, bez node_modules) ----------------------
FROM base AS app

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=assets /var/www/html/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/prod/start-container /usr/local/bin/start-container
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/prod/php.ini /etc/php/8.4/cli/conf.d/99-app.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80

ENTRYPOINT ["start-container"]
