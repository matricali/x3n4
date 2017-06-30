FROM php:5.6-apache

MAINTAINER Jorge Matricali <jorgematricali@gmail.com>

RUN apt-get update && apt-get install -y libcurl4-openssl-dev libxml2-dev
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql curl json xml

RUN apt-get install -y zlib1g zlib1g-dbg zlib1g-dev zlibc
RUN docker-php-ext-install -j$(nproc) zip

RUN a2enmod headers && \
    a2enmod rewrite

RUN apt-get autoremove -y && \
    apt-get autoclean -y && \
    apt-get clean -y && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /etc/php5 /etc/php/5* /usr/lib/php/20121212 /usr/lib/php/20131226

RUN rm -rf /var/log /var/cache
