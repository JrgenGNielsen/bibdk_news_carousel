FROM docker-dscrum.dbc.dk/infomedia
 
RUN apt-get update && apt-get -q -y install php-mbstring phpunit

RUN rm -r /var/www
ADD www /var/www/html
COPY infomedia.ini /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

COPY run-unittest.sh /usr/bin/run-unittest.sh
RUN chmod 755 /usr/bin/run-unittest.sh

CMD ["/bin/bash", "-c", "/usr/bin/run-unittest.sh"]

