# hadolint global ignore=DL3008

FROM dunglas/frankenphp:1.4.4-php8.4-bookworm as php-base

RUN install-php-extensions \
        intl \
        opcache \
        pdo_pgsql \
        uuid

COPY ./base/php.ini $PHP_INI_DIR/conf.d/app-base.ini

# Fake user to maps with the one on the host
ARG USER_ID
RUN addgroup --gid $USER_ID app && \
    adduser --system --uid $USER_ID --home /home/app --shell /bin/bash app && \
    curl -Ls https://github.com/tianon/gosu/releases/download/1.17/gosu-amd64 | \
        install /dev/stdin /usr/local/bin/gosu

COPY entrypoint /

ENTRYPOINT ["/entrypoint"]

FROM php-base AS frontend

RUN  setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp && \
    chown -R ${USER_ID}:${USER_ID} /data/caddy && \
    chown -R ${USER_ID}:${USER_ID} /config/caddy

COPY ./frontend/etc/. /etc/
COPY ./frontend/php.ini $PHP_INI_DIR/conf.d/app-frontend.ini

WORKDIR /var/www

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]

EXPOSE 80

FROM php-base AS worker

FROM php-base AS builder

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Default toys
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        gnupg \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

ARG NODEJS_VERSION=20.x
RUN curl -s https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODEJS_VERSION} nodistro main" > /etc/apt/sources.list.d/nodesource.list

# Default toys
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash-completion \
        ca-certificates \
        curl \
        git \
        make \
        nodejs \
        procps \
        sudo \
        unzip \
    && apt-get clean \
    && npm install -g yarn@1.22 \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Composer
COPY --from=composer/composer:2.8.4 /usr/bin/composer /usr/bin/composer

ADD https://raw.githubusercontent.com/symfony/symfony/refs/heads/7.2/src/Symfony/Component/Console/Resources/completion.bash /tmp/completion.bash

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
COPY builder/etc/. /etc/
RUN adduser app sudo \
    && mkdir /var/log/php \
    && chmod 777 /var/log/php

# Third party tools
ENV PATH="$PATH:/var/www/tools/bin"

# Good default customization
RUN cat >> /etc/bash.bashrc <<EOF
. /etc/bash_completion

PS1='\[\e[01;33m\]\u \[\e[00;32m\]\w\[\e[0m\] '
EOF

WORKDIR /var/www
