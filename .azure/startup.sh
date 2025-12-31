# Update repo
apt update
# Enable WebP support
apt install -y libfreetype6-dev libjpeg62-turbo-dev libwebp-dev libpng-dev
docker-php-ext-configure gd --with-webp --with-jpeg --with-freetype
docker-php-ext-install gd
# Install nginx headers more filter
apt install -y libnginx-mod-http-headers-more-filter
# Copy nginx conf to hide header
cp /home/site/wwwroot/.azure/nginx.conf /etc/nginx/nginx.conf
# Copy site conf to allow root in subfolder
cp /home/site/wwwroot/.azure/default.conf /etc/nginx/conf.d/default.conf
# Restart service
service nginx restart