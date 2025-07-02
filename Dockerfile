# Multi-stage build for production optimization
FROM php:8.3-apache as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Fix git ownership issues
RUN git config --global --add safe.directory /var/www/html

# Copy composer files first for better caching
COPY composer.json ./
# Note: Not copying composer.lock to allow fresh dependency resolution

# Install dependencies without lock file for compatibility
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Create logs directory with proper permissions
RUN mkdir -p logs && chown -R www-data:www-data logs/ && chmod 755 logs/

# Configure Apache
RUN a2enmod rewrite headers
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Copy environment file if exists
RUN if [ -f .env.example ]; then cp .env.example .env; fi

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/api/v1/test || exit 1

# Expose port
EXPOSE 80

# Production stage
FROM base as production

# Remove development files
RUN rm -rf tests/ docker/ .git/ .github/ \
    && composer install --no-dev --optimize-autoloader --no-scripts

# Start Apache
CMD ["apache2-foreground"]

# Development stage
FROM base as development

# Install development dependencies
RUN composer install --optimize-autoloader

# Install Xdebug for development
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Configure Xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

CMD ["apache2-foreground"]