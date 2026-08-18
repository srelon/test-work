FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
      apt-utils \
      libpq-dev \
      libpng-dev \
      libzip-dev \
      libwebp-dev \
      libjpeg62-turbo-dev \
      libfreetype6-dev \
      libicu-dev \
      zip unzip \
      git \
      curl && \
      docker-php-ext-configure gd \
          --with-freetype \
          --with-jpeg \
          --with-webp && \
      docker-php-ext-install pdo_mysql bcmath gd zip intl && \
      apt-get clean && \
      rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Node.js 20.x and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*


COPY ./_docker/app/php.ini /usr/local/etc/php/conf.d/php.ini

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

# Match www-data UID/GID to host user (1000:1000)
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

WORKDIR /var/www

USER www-data
