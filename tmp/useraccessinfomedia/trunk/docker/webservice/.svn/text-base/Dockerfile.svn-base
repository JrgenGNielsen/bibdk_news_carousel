FROM docker.dbc.dk/dbc-stretch
 
RUN apt-get update && apt-get -q -y install apache2 apache2-doc php php-curl php-memcache memcached php-xml ca-certificates 

RUN a2enmod rewrite && a2enmod headers
 
ADD fqdn.conf /etc/apache2/conf-available/
RUN rm /etc/apache2/sites-available/000-default.conf
COPY 000-default.conf /etc/apache2/sites-available
RUN a2enconf fqdn
 
RUN rm -r /var/www
ADD www /var/www/html
COPY infomedia.ini /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

COPY run-infomedia.sh /usr/bin/run-infomedia.sh
RUN chmod 755 /usr/bin/run-infomedia.sh

CMD ["/bin/bash", "-c", "/usr/bin/run-infomedia.sh"]

