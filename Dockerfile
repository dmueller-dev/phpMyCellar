# ==============================================================================
# phpMyCellar - Container Image Dockerfile
# ==============================================================================

FROM php:8.2-apache

# Install OS libraries for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo_mysql \
        mbstring \
        gd \
        opcache \
        fileinfo \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules for clean URLs, security headers, and caching
RUN a2enmod rewrite headers expires

# Recommended PHP production settings
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'upload_max_filesize=16M'; \
    echo 'post_max_size=20M'; \
    echo 'memory_limit=128M'; \
} > /usr/local/etc/php/conf.d/phpmycellar.ini

# Set default working directory
WORKDIR /var/www/html

# Expose HTTP port
EXPOSE 80

CMD ["apache2-foreground"]
