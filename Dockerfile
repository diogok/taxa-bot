FROM diogok/php7

WORKDIR /var/www
CMD ["php","/var/www/html/dwca2sql.php"]

COPY . /var/www
RUN chown www-data.www-data /var/www -Rf

