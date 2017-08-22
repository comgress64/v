# Use phusion/baseimage as base image. To make your builds
# reproducible, make sure you lock down to a specific version, not
# to `latest`! See
# https://github.com/phusion/baseimage-docker/blob/master/Changelog.md
# for a list of version numbers.
FROM phusion/baseimage:0.9.22
ENV DEBIAN_FRONTEND noninteractive

# Use baseimage-docker's init system.
CMD ["/sbin/my_init"]

# ...put your own build instructions here...

# Clean up APT when done.

ARG stage

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*:
RUN apt-get update
RUN apt-get update && apt-get install -y \
        libapache2-mod-php \
        openssh-server \
        libfreetype6-dev \
        libmcrypt-dev \
        libpng12-dev \
        curl \
        git \
        wget \
        libcurl3-dev \
        libicu-dev \
        libxml2 \
        libxml2-dev \
        libxslt1-dev \
        libxslt-dev \
        vim-tiny \
        mysql-client 
RUN rm -rf /var/www/html
RUN apt-get update && apt-get install -y libmagickwand-dev imagemagick  
RUN curl -sL https://deb.nodesource.com/setup_7.x -o nodesource_setup.sh && chmod +x nodesource_setup.sh && ./nodesource_setup.sh
RUN apt-get install -y nodejs bzip2
RUN npm install -g gulp-cli grunt-cli node-sass
RUN npm -g i webpack@\^2.2
RUN apt-get install -y php apache2 php-mysql php-mbstring php-curl
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" 
RUN php -r "if (hash_file('SHA384', 'composer-setup.php') === '669656bab3166a7aff8a7506b8cb2d1c292f042046c5a994c43155c0be6190fa0355160742ab2e1c88d40d5be660b410') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"  \
    && php composer-setup.php --install-dir=/usr/bin \
    && php -r "unlink('composer-setup.php');" \
    && mv /usr/bin/composer.phar /usr/bin/composer \
    && chmod +x  /usr/bin/composer 
RUN apt-get install -y php-xml zip unzip php-zip iputils-ping php-mbstring php-mcrypt php-pear php-mysqlnd php-pdo php-soap php-opcache php-pspell php-tidy php-xml
ADD . /var/www
RUN ln -s /var/www/public /var/www/html
RUN mkdir /var/www/.composer && chown -R www-data /var/www/
USER www-data
RUN cp /var/www/.env.example /var/www/.env
RUN cd /var/www && composer update && php artisan key:generate
USER root
#COPY ci/authorized_keys /root/.ssh/
#RUN chown 600 /root/.ssh/authorized_keys
COPY ci/000-default.conf /etc/apache2/sites-enabled/
RUN chown -R www-data /var/www/storage

RUN mkdir /etc/service/apache
COPY ci/apache.sh /etc/service/apache/run
RUN chmod +x /etc/service/apache/run
RUN a2enmod rewrite

RUN rm -f /etc/service/sshd/down
RUN /etc/my_init.d/00_regen_ssh_host_keys.sh
