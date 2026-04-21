# Magento 2 Development Dockerfile
# PHP 8.3 with Apache - suitable for Magento 2.4.8+
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libxslt-dev \
    libonig-dev \
    libftp-dev \
    git \
    curl \
    wget \
    unzip \
    cron \
    redis-tools \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    zip \
    intl \
    gd \
    bcmath \
    soap \
    xsl \
    mbstring \
    xml \
    opcache \
    ftp \
    sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers expires ssl

# Configure PHP
RUN echo "memory_limit = 2G" >> /usr/local/etc/php/conf.d/memory.ini \
    && echo "max_execution_time = 1800" >> /usr/local/etc/php/conf.d/time.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/conf.d/errors.ini \
    && echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption = 512" >> /usr/local/etc/php/conf.d/opcache.ini

# Create Magento directory structure
RUN mkdir -p /var/www/html/pub /var/www/html/var /var/www/html/generated /var/www/html/app /var/www/html/lib /var/www/html/setup /var/www/html/update \
    && mkdir -p /var/www/html/pub/static \
    && chmod -R 777 /var/www/html/var /var/www/html/generated /var/www/html/pub

# Copy Apache config
COPY docker/apache2-magento.conf /etc/apache2/sites-available/000-default.conf

# Copy cron entry
COPY docker/cron /etc/cron.d/magento-cron
RUN chmod 0644 /etc/cron.d/magento-cron

# Set proper ownership
RUN chown -R www-data:www-data /var/www/html

# Expose ports
EXPOSE 80 443

# Start Apache
CMD ["apache2-foreground"]