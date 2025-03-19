# Copy nginx conf to enable gzip
cp /home/site/wwwroot/.azure/nginx.conf /etc/nginx/nginx.conf
# Copy site conf to allow root in subfolder
cp /home/site/wwwroot/.azure/default.conf /etc/nginx/sites-enabled/default
apt update
# Enable WebP support
apt install libfreetype6-dev libjpeg62-turbo-dev libwebp-dev libpng-dev
docker-php-ext-configure gd --with-webp --with-jpeg --with-freetype
docker-php-ext-install gd
# Install cron
apt-get update -qq && apt-get install cron -yqq
# Starting cron
service cron start
# Cron scheduler for WP
(crontab -l 2>/dev/null; echo "*/10 * * * * /usr/local/bin/php /home/site/wwwroot/public/edition/wp-cron.php > /dev/null 2>&1")|crontab
# Restart service
service nginx restart