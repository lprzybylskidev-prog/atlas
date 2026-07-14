FROM nginx:1.29-bookworm

COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html

COPY docker/production/nginx/public /var/www/html/public
