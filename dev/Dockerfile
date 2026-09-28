FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends unzip imagemagick libonig-dev \
    && docker-php-ext-install mysqli pdo_mysql mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
RUN curl -fsSL https://github.com/omeka/Omeka/releases/download/v3.1.2/omeka-3.1.2.zip -o /tmp/omeka.zip \
    && unzip -q /tmp/omeka.zip -d /tmp \
    && cp -a /tmp/omeka-3.1.2/. /var/www/html/ \
    && curl -fsSL https://github.com/omeka/plugin-Geolocation/releases/download/v3.3/Geolocation-3.3.zip -o /tmp/geolocation.zip \
    && unzip -q /tmp/geolocation.zip -d /var/www/html/plugins \
    && chown -R www-data:www-data /var/www/html/files \
    && rm -rf /tmp/omeka.zip /tmp/omeka-3.1.2 /tmp/geolocation.zip
COPY db.ini /var/www/html/db.ini
RUN printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/omeka.conf \
    && a2enconf omeka
