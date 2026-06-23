FROM php:8.1-alpine
ENV XDEBUG_VERSION=3.4.2
ARG GITHUB_TOKEN

RUN apk add --no-cache nano icu-dev linux-headers $PHPIZE_DEPS
RUN pecl install xdebug-${XDEBUG_VERSION}
RUN docker-php-ext-install intl
RUN docker-php-ext-enable xdebug

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer config --global github-oauth.github.com ${GITHUB_TOKEN}
RUN composer self-update

ENTRYPOINT ["composer"]

