FROM php:8.3-fpm

# Установите необходимые зависимости и pdo_pgsql
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql

# Копируйте проект
WORKDIR /var/task/user
COPY . .

# Установите Composer зависимости
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Настройте точку входа
CMD ["php", "public/index.php"]
