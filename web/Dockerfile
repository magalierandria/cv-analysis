FROM debian:bookworm-slim AS cssbuild

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sLo /usr/local/bin/tailwindcss \
      https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-linux-x64 \
    && chmod +x /usr/local/bin/tailwindcss

WORKDIR /build
COPY assets/ assets/
COPY public/Views/ public/Views/
RUN mkdir -p public/css \
    && tailwindcss -c assets/tailwind.config.js -i assets/css/input.css -o public/css/app.css --minify

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Désactive les connexions persistantes : sous WSL2, le tunnel réseau
# Windows<->WSL2 peut faire retomber une requête sur une connexion keep-alive
# qu'Apache vient de fermer (KeepAliveTimeout par défaut = 5s). Le navigateur
# retente silencieusement les GET mais pas les POST, ce qui fait échouer
# aléatoirement la soumission du formulaire de login.
RUN echo 'KeepAlive Off' > /etc/apache2/conf-enabled/keepalive-off.conf

RUN { \
    echo 'upload_max_filesize = 12M'; \
    echo 'post_max_size = 12M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

RUN { \
    echo 'session.cookie_httponly = 1'; \
    echo 'session.cookie_samesite = Lax'; \
    } > /usr/local/etc/php/conf.d/session.ini

COPY public/ /var/www/html/
COPY --from=cssbuild /build/public/css/app.css /var/www/html/css/app.css
