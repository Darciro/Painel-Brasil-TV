# syntax=docker/dockerfile:1

# ---- Theme assets (Tailwind/Vite build) ----
FROM node:20-alpine AS theme-assets

WORKDIR /app

COPY wp-content/themes/pbtv/package.json wp-content/themes/pbtv/package-lock.json ./
RUN npm ci

COPY wp-content/themes/pbtv/ ./
RUN npm run build


# ---- WordPress runtime ----
FROM wordpress:php8.3-apache

# Raise upload/memory limits above the image defaults.
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# The base image copies /usr/src/wordpress into /var/www/html on container
# start whenever the target is empty, so custom plugins/themes are staged
# there rather than under /var/www/html directly (which isn't part of the
# image, only of the running container's writable layer / volumes).
COPY wp-content/plugins/index.php /usr/src/wordpress/wp-content/plugins/index.php
COPY wp-content/plugins/hello.php /usr/src/wordpress/wp-content/plugins/hello.php
COPY wp-content/plugins/akismet/ /usr/src/wordpress/wp-content/plugins/akismet/

COPY wp-content/themes/pbtv/ /usr/src/wordpress/wp-content/themes/pbtv/
COPY --from=theme-assets /app/assets/dist/ /usr/src/wordpress/wp-content/themes/pbtv/assets/dist/

RUN rm -rf /usr/src/wordpress/wp-content/themes/pbtv/node_modules
