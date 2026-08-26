# hadolint global ignore=DL3008

FROM dunglas/frankenphp:1.12.7-php8.5 AS php-base

RUN install-php-extensions \
        intl \
        opcache \
        pdo_pgsql \
        uuid

COPY ./base/php.ini $PHP_INI_DIR/conf.d/app-base.ini

ENV HOME=/home/app

WORKDIR /var/www

FROM php-base AS frontend

RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp && \
    chmod -R 777 /data /config

COPY ./frontend/etc/. /etc/
COPY ./frontend/php.ini $PHP_INI_DIR/conf.d/app-frontend.ini

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]

EXPOSE 80

FROM php-base AS worker

# Remove caddy healthcheck inherited from frankenphp image
HEALTHCHECK NONE

FROM php-base AS builder

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Default toys
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash-completion \
        ca-certificates \
        curl \
        git \
        make \
        procps \
        unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Composer
COPY --from=composer/composer:2.10.0 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_MEMORY_LIMIT=-1

ADD https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/src/Symfony/Component/Console/Resources/completion.bash /tmp/completion.bash

# Composer symfony/console version is too old, and doest not support "API version feature", so we remove it
# Hey, while we are at it, let's add some more completion
RUN sed /tmp/completion.bash \
        -e "s/{{ COMMAND_NAME }}/composer/g" \
        -e 's/"-a{{ VERSION }}"//g' \
        -e "s/{{ VERSION }}/1/g"  \
        > /etc/bash_completion.d/composer \
    && sed /tmp/completion.bash \
        -e "s/{{ COMMAND_NAME }}/console/g" \
        -e "s/{{ VERSION }}/1/g"  \
        > /etc/bash_completion.d/console

# Config
COPY ./builder/php.ini $PHP_INI_DIR/conf.d/app-builder.ini
RUN mkdir /var/log/php \
    && chmod 777 /var/log/php

# Third party tools
ENV PATH="$PATH:/var/www/tools/bin"

# frankenphp defined the two following vars. But they are located in a folder
# not owned by the user (/data, and /config). Yarn needs it, and it must be
# shared between all containers instances! So let's override(reset) them to
# point to the user home directory (default values)
ENV XDG_DATA_HOME=
ENV XDG_CONFIG_HOME=

# Good default customization
RUN cat >> /etc/bash.bashrc <<EOF
. /etc/bash_completion

PS1='\[\e[01;33m\]\u \[\e[00;32m\]\w\[\e[0m\] '
EOF
