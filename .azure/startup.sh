# Update repo
apt update
# Enable WebP support
apt install -y libfreetype6-dev libjpeg62-turbo-dev libwebp-dev libpng-dev
docker-php-ext-configure gd --with-webp --with-jpeg --with-freetype
docker-php-ext-install gd
# Install nginx headers more filter
apt install -y libnginx-mod-http-headers-more-filter
# Install cron
apt install -y cron
# Starting cron
service cron start
# Cron scheduler for WP
(crontab -l 2>/dev/null; echo "*/10 * * * * /usr/local/bin/php /home/site/wwwroot/public/edition/wp-cron.php > /dev/null 2>&1")|crontab
# Copy nginx conf to hide header
cp /home/site/wwwroot/.azure/nginx.conf /etc/nginx/nginx.conf
# Copy site conf to allow root in subfolder
cp /home/site/wwwroot/.azure/default.conf /etc/nginx/conf.d/default.conf
# Restart service
service nginx restart