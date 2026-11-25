# Image staging: PHP 8.2 + Apache + driver SQL Server (sqlsrv). BELUM diuji di mesin pembuat; uji dulu di staging.
FROM php:8.2-apache
RUN apt-get update && apt-get install -y gnupg2 curl unixodbc-dev apt-transport-https \
 && curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > /usr/share/keyrings/microsoft.gpg \
 && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql.list \
 && apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
 && pecl install sqlsrv pdo_sqlsrv && docker-php-ext-enable sqlsrv pdo_sqlsrv \
 && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html
RUN sed -i 's#/var/www/html#/var/www/html#g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
EXPOSE 80
