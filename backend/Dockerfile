FROM php:8.2-apache

# Enable Apache mod_rewrite (required for .htaccess routing)
RUN a2enmod rewrite

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# PHP ini: ensure $_ENV is populated from system environment variables.
# variables_order = EGPCS  (Environment, Get, Post, Cookie, Server)
RUN echo "variables_order = EGPCS" > /usr/local/etc/php/conf.d/taskflow.ini \
 && echo "expose_php = Off"       >> /usr/local/etc/php/conf.d/taskflow.ini

# Write Apache vhost — AllowOverride All enables .htaccess mod_rewrite routing.
# Port is set dynamically at startup via docker-entrypoint.sh (for Railway).
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Copy backend source files into document root
COPY . /var/www/html/

# Copy and set up the entrypoint script
RUN chmod +x /var/www/html/docker-entrypoint.sh

# Fix ownership
RUN chown -R www-data:www-data /var/www/html

# Railway dynamically assigns PORT — entrypoint patches Apache to use it
EXPOSE 80

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
