FROM debian:buster-slim

ENV key=value

RUN apt-get update \
    && apt install -y --no-install-recommends \
        ca-certificates \
        gnupg \
    && echo "deb https://packages.sury.org/php buster main" > /etc/apt/sources.list.d/sury.list \
    && apt-key adv --keyserver keyserver.ubuntu.com --recv-keys B188E2B695BD4743 \
    && apt-get update

RUN apt-get install -y --no-install-recommends \
        procps

ARG PHP_VERSION=7.4
RUN apt-get install -y --no-install-recommends \
        php${PHP_VERSION}-amqp \
        php${PHP_VERSION}-apcu \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-iconv \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-uuid \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Fake user to maps with the one on the host
ARG USER_ID
RUN addgroup --gid 1000 app && \
    adduser --system --uid $USER_ID --home /home/app --shell /bin/bash app

# Configuration
COPY etc/. /etc/
