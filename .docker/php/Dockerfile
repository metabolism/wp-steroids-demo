FROM node:16 AS node
FROM php:8.2-fpm

# Set the working directory
WORKDIR /var/www/html

# Update package lists
RUN apt-get update

# Install required dependencies
RUN apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev curl git zip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mysqli
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install -j$(nproc) gd

# Run Composer to install dependencies
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Allow Composer to run as superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Set the ACF_PRO_KEY build argument
ARG ACF_PRO_KEY

# Copy installation files
COPY composer.* ./

# Run Composer to install dependencies
RUN composer install --prefer-dist --no-dev --no-interaction
RUN composer update --prefer-dist --no-dev --no-interaction

COPY . /var/www/html

# Optimizing Composer autoloader for better performance
RUN composer dump-autoload --optimize

# Run Node to install dependencies
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

RUN chmod -R 0777 public/build

# Install app dependencies
RUN npm install

# Run npm run build during the build process
RUN npm run build

# Copying the files into the Docker image
COPY public/build public/build