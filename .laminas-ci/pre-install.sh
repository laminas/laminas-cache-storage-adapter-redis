#!/bin/bash

JOB=$3

COMMAND=$(echo "${JOB}" | jq -r '.command // ""')
[[ "${COMMAND}" =~ ^REDIS_VERSION=([0-9\.]+) ]] || exit 0

PHP=$(echo "${JOB}" | jq -r '.php // ""')
REDIS_VERSION=${BASH_REMATCH[1]}

pecl install -f --configureoptions 'enable-redis-igbinary="yes" enable-redis-lzf="yes"' igbinary redis-${REDIS_VERSION}
echo "extension=redis.so" > /etc/php/${PHP}/mods-available/redis.ini
