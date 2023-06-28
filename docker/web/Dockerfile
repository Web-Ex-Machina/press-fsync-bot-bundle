ARG PHP_VERSION
FROM php:${PHP_VERSION}-apache
ARG CONTAO_VERSION
ARG WORKDIR_CONTAO
ARG APP_SECRET
ARG BASE_URL
ARG BUNDLE_VERSION

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libicu-dev \
        zip \
        unzip \
        libzip-dev \
        libxml2-dev \
        libgmp-dev \
        re2c \
        libmhash-dev \
		file \
		git \
		iputils-ping \
		cron \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-configure pdo \
    && docker-php-ext-install pdo \
    && docker-php-ext-configure pdo_mysql \
    && docker-php-ext-install pdo_mysql\
    && docker-php-ext-configure gmp \
    && docker-php-ext-install gmp \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ADD bot_github_prod8 /ssh/github_key
ADD bot_github_prod8.pub /ssh/github_key.pub

RUN echo WORKDIR_CONTAO : $WORKDIR_CONTAO
RUN mkdir -p $WORKDIR_CONTAO \
    # We give the www-data the same user UUID as ours
    && chown -R www-data:www-data /usr/local/bin/composer \
	&& chown -R www-data:www-data /ssh/ \
	&& chmod +x /usr/local/bin/composer \
    && chown -R www-data:www-data $WORKDIR_CONTAO \
	&& chown -R www-data:www-data /var/www

RUN sed -i -e "s@DocumentRoot .*@DocumentRoot $WORKDIR_CONTAO/public@" -e "s@#ServerName www.example.com@ServerName $BASE_URL@" /etc/apache2/sites-available/000-default.conf 

USER www-data

RUN cd $WORKDIR_CONTAO \
 && composer create-project contao/managed-edition $WORKDIR_CONTAO $CONTAO_VERSION \
 && mkdir ~/.ssh \
 && echo "Host github.com\n  HostName github.com\n  IdentityFile /ssh/github_key" > ~/.ssh/config \
 && cat ~/.ssh/config \
 && ssh-keyscan -t rsa github.com >> ~/.ssh/known_hosts

RUN cd $WORKDIR_CONTAO \
 && composer config repositories.bot '{"type": "vcs", "url": "git@github.com:Web-Ex-Machina/press-fsync-bot-bundle"}' \
 && composer require "webexmachina/press-fsync-bot-bundle:$BUNDLE_VERSION"

ADD --chown=www-data:www-data https://download.contao.org/contao-manager/stable/contao-manager.phar $WORKDIR_CONTAO/public/contao-manager.phar.php

RUN echo "TRUSTED_PROXIES=REMOTE_ADDR\nHTTP_HOST=$BASE_URL\nSERVER_NAME=$BASE_URL\nSERVER_PORT=443\nAPP_SECRET=$APP_SECRET" > $WORKDIR_CONTAO/.env

RUN crontab -l | { cat; echo "5 * * * * php /api/syncschedule"; } | crontab -
RUN crontab -l | { cat; echo "5 * * * * php /api/executetasks"; } | crontab -
