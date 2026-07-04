FROM php:8.3-apache

# MySQL support (pdo_sqlite already ships enabled in the base image, which
# is what powers the automatic fallback database used when no MySQL is
# configured).
RUN docker-php-ext-install pdo_mysql mysqli

# Apache modules used by public/.htaccess (gzip compression + cache headers)
RUN a2enmod rewrite headers expires deflate

# Apache prints a harmless startup warning without a ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# App code
COPY . /var/www/html

# The app's entry point is public/, not the project root
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Writable storage for uploads + the SQLite fallback DB.
# In render.yaml this whole folder is a persistent disk mount, so its
# contents survive restarts and redeploys.
RUN mkdir -p /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
