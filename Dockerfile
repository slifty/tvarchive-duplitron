FROM php:5

RUN apt-get update
RUN apt-get -y install git apache2 postgresql php5-common libapache2-mod-php5 php5-cli curl php5-pgsql yasm libx264-dev cmake mercurial libmp3lame-dev libopus-dev php5-mysql
RUN apt-get -y install wget autotools-dev automake autoconf dh-autoreconf libgmp-dev libass-dev zip supervisor libtheora-dev libvorbis-dev

# ffmpeg

RUN mkdir ~/ffmpeg_sources
RUN cd ~/ffmpeg_sources ; hg clone https://bitbucket.org/multicoreware/x265 ; cd ~/ffmpeg_sources/x265/build/linux ; PATH="$HOME/bin:$PATH" cmake -G "Unix Makefiles" -DCMAKE_INSTALL_PREFIX="$HOME/ffmpeg_build" -DENABLE_SHARED:bool=off ../../source ; make ; make install #; make distclean
RUN cd ~/ffmpeg_sources ; wget -O fdk-aac.tar.gz https://github.com/mstorsjo/fdk-aac/tarball/master ; tar xzvf fdk-aac.tar.gz ; cd mstorsjo-fdk-aac* ; autoreconf -fiv ; ./configure --prefix="$HOME/ffmpeg_build" --disable-shared ; make ; make install ; make distclean
RUN cd ~/ffmpeg_sources ; wget http://storage.googleapis.com/downloads.webmproject.org/releases/webm/libvpx-1.5.0.tar.bz2 ; tar xjvf libvpx-1.5.0.tar.bz2 ; cd libvpx-1.5.0 ; PATH="$HOME/bin:$PATH" ./configure --prefix="$HOME/ffmpeg_build" --disable-examples --disable-unit-tests ; PATH="$HOME/bin:$PATH" make ; make install ; make clean
RUN cd ~/ffmpeg_sources ; wget http://ffmpeg.org/releases/ffmpeg-snapshot.tar.bz2 ; tar xjvf ffmpeg-snapshot.tar.bz2 ; cd ffmpeg ; PATH="$HOME/bin:$PATH" PKG_CONFIG_PATH="$HOME/ffmpeg_build/lib/pkgconfig" ./configure  --prefix="$HOME/ffmpeg_build" --pkg-config-flags="--static" --extra-cflags="-I$HOME/ffmpeg_build/include" --extra-ldflags="-L$HOME/ffmpeg_build/lib" --bindir="$HOME/bin" --enable-gpl --enable-libass --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libtheora --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265 --enable-nonfree ; PATH="$HOME/bin:$PATH" make ; make install ; make distclean ; hash -r
RUN cp ~/bin/ffmpeg /usr/local/bin
RUN cp ~/bin/ffprobe /usr/local/bin


RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY docker.env /usr/src/app/.env
RUN apt-get install -y frei0r-plugins git python python-scipy python-pip python-matplotlib software-properties-common wget libfreetype6-dev libpng-dev pkg-config python-dev

RUN pip install distribute
RUN pip install docopt git+git://github.com/bmcfee/librosa.git joblib


RUN cd /usr/local/src ; wget --no-check-certificate http://www.mega-nerd.com/SRC/libsamplerate-0.1.8.tar.gz ; tar xvfz libsamplerate-0.1.8.tar.gz ; cd libsamplerate-0.1.8 && ./configure && make && make install
RUN pip install scikits.samplerate
RUN cd /usr/local/src ; git clone https://github.com/dpwe/audfprint.git


RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php -r "if (hash_file('SHA384', 'composer-setup.php') === 'e115a8dc7871f15d853148a7fbac7da27d6c0030b848d9b3dc09e2a0388afed865e6a3d6b3c0fad45c48e2b5fc1196ae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && php composer-setup.php 
RUN php -r "unlink('composer-setup.php');"

COPY . /usr/src/app
RUN php composer.phar install

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN cd /var/www ; git clone https://github.com/slifty/tvarchive-fingerprinting.git

RUN chown -R www-data:www-data /var/www/tvarchive-fingerprinting/
RUN cd /var/www/tvarchive-fingerprinting ; composer install
RUN printf "<VirtualHost *:80>\n\tDocumentRoot /var/www/tvarchive-fingerprinting/public\n\tErrorLog \${APACHE_LOG_DIR}/error.log\n\tCustomLog \${APACHE_LOG_DIR}/access.log combined\n</VirtualHost>" | tee /etc/apache2/sites-available/tvarchive-fingerprinting.conf
COPY docker-apache2.conf /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/tvarchive-fingerprinting/
RUN apt-get install -y supervisor sudo # we need sudo for various things, maybe
RUN cp /var/www/tvarchive-fingerprinting/fingerprinting-worker.conf.example /etc/supervisor/conf.d
RUN a2dissite 000-default
RUN a2ensite tvarchive-fingerprinting
RUN a2enmod rewrite
RUN apachectl restart

RUN apt-get install -y libpq-dev
RUN docker-php-ext-install pdo_pgsql

# postgres is handled by another container
RUN chmod 777 -R /var/www/tvarchive-fingerprinting/storage/audfprint
RUN chmod 777 -R /var/www/tvarchive-fingerprinting/storage/logs
RUN cp /usr/src/app/.env /var/www/tvarchive-fingerprinting/.env

# these have to get run in the container, with `$ docker-compose run web bash`
# -----------
# createdb -U postgres --host=postgres duplitronfingerprinting 
# createuser -U postgres --host=postgres duplitronfingerprinting -P
# psql  -U postgres --host=postgres -c "GRANT ALL PRIVILEGES ON DATABASE duplitronfingerprinting to duplitronfingerprinting"
# php /var/www/tvarchive-fingerprinting/artisan key:generate
# php /var/www/tvarchive-fingerprinting/artisan migrate:refresh
# sudo service supervisor start
# supervisorctl reread
# supervisorctl update
# supervisorctl start fingerprinting-worker:*
