# The Duplitron 5000

This repository contains the Duplitron, a RESTful API that makes it possible to discover and manage repeated audio from a larger corpus.  It was originally created to support the Internet Archive's [Political Ad Archive](https://politicaladarchive.org/).

The Duplitron takes in media (audio or video), compares it with existing media, and categorizes sections of the media based on the result of that comparison.  It uses [ffmpeg](https://ffmpeg.org/) to manipulate the media files and then uses [audfprint](https://github.com/dpwe/audfprint) (via a docker image) to process the media files and identify segments of repeated content.

The [API itself](docs/api.md) is powered by [Laravel](http://laravel.com).

The code is written to support [PSR-2](http://www.php-fig.org/psr/psr-2/index.html)

## Dependencies

To run this code you need:

* PHP >= 5.5.9
* OpenSSL PHP Extension
* PDO PHP Extension
* Mbstring PHP Extension
* Tokenizer PHP Extension
* [Composer](https://getcomposer.org/) for dependency management
* [Docker](https://www.docker.com/) for running audfprint
* [ffmpeg](https://ffmpeg.org/)
* [PSQL](http://www.postgresql.org/)

## Installing

1. Install [ffmpeg](https://ffmpeg.org/)

2. Install [Docker](https://www.docker.com/) for running audfprint

3. Make it possible for your web provider to talk to docker.  You can either create a docker group and add your web user to that group (easier), or set up [TLS verification](https://docs.docker.com/engine/articles/https/) for docker (harder).

4. Install [Composer](https://getcomposer.org/)

5. Clone the audfprint docker image and make sure it runs

	```shell
	docker pull slifty/audfprint
	docker run slifty/audfprint
	```

6. Clone this repository into a living breathing live php enabled directory mod rewrite should be enabled

7. Install composer dependencies

	```shell
		cd /path/to/your/clone/here
		composer install
	```

8. Copy .env.example to .env in the root directory, then edit it

	```shell
		cd /path/to/your/clone/here
		cp .env.example .env
		vi .env
	```

	* RSYNC_IDENTITY_FILE: a path to a private key that web root has 500 access to, with any media files you plan on importing
	* FPRINT_STORE: a path to the /storage/audfprint director inside of the repository
	* DOCKER_HOST: the location and port of the docker you set up for audfprint

9. Install supervisor and [enable the job queue](http://laravel.com/docs/5.1/queues#running-the-queue-listener).

	```shell
		cp duplitron-worker.conf.example /etc/supervisor/conf.d/duplitron-worker.conf
		vi /etc/supervisor/conf.d/duplitron-worker.conf
		sudo supervisorctl reread
		sudo supervisorctl update
		sudo supervisorctl start duplitron-worker:*
	```


// Run ssh -fN -o"ControlPath none" -L 9999:tv-se.archive.org:8983 vm-home1.archive.org
// so that requests are proxied through





## Installing on a fresh copy of ubuntu 14.04...

```shell
sudo apt-get update
sudo apt-get -y install git apache2 postgresql php5-common libapache2-mod-php5 php5-cli curl php5-pgsql
sudo apt-get -y --force-yes install autoconf automake build-essential libass-dev libfreetype6-dev libsdl1.2-dev libtheora-dev libtool libva-dev libvdpau-dev libvorbis-dev libxcb1-dev libxcb-shm0-dev libxcb-xfixes0-dev pkg-config texinfo zlib1g-dev
sudo apt-get install yasm libx264-dev cmake mercurial libmp3lame-dev libopus-dev
mkdir ~/ffmpeg_sources
cd ~/ffmpeg_sources ; hg clone https://bitbucket.org/multicoreware/x265 ; cd ~/ffmpeg_sources/x265/build/linux ; PATH="$HOME/bin:$PATH" cmake -G "Unix Makefiles" -DCMAKE_INSTALL_PREFIX="$HOME/ffmpeg_build" -DENABLE_SHARED:bool=off ../../source ; make ; make install ; make distclean
cd ~/ffmpeg_sources ; wget -O fdk-aac.tar.gz https://github.com/mstorsjo/fdk-aac/tarball/master ; tar xzvf fdk-aac.tar.gz ; cd mstorsjo-fdk-aac* ; autoreconf -fiv ; ./configure --prefix="$HOME/ffmpeg_build" --disable-shared ; make ; make install ; make distclean
cd ~/ffmpeg_sources ; wget http://storage.googleapis.com/downloads.webmproject.org/releases/webm/libvpx-1.5.0.tar.bz2 ; tar xjvf libvpx-1.5.0.tar.bz2 ; cd libvpx-1.5.0 ; PATH="$HOME/bin:$PATH" ./configure --prefix="$HOME/ffmpeg_build" --disable-examples --disable-unit-tests ; PATH="$HOME/bin:$PATH" make ; make install ; make clean
cd ~/ffmpeg_sources ; wget http://ffmpeg.org/releases/ffmpeg-snapshot.tar.bz2 ; tar xjvf ffmpeg-snapshot.tar.bz2 ; cd ffmpeg ; PATH="$HOME/bin:$PATH" PKG_CONFIG_PATH="$HOME/ffmpeg_build/lib/pkgconfig" ./configure  --prefix="$HOME/ffmpeg_build" --pkg-config-flags="--static" --extra-cflags="-I$HOME/ffmpeg_build/include" --extra-ldflags="-L$HOME/ffmpeg_build/lib" --bindir="$HOME/bin" --enable-gpl --enable-libass --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libtheora --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265 --enable-nonfree ; PATH="$HOME/bin:$PATH" make ; make install ; make distclean ; hash -r
sudo cp ~/bin/ffmpeg /usr/local/bin
sudo cp ~/bin/ffprobe /usr/local/bin
sudo apt-get install -y frei0r-plugins git python python-scipy python-pip python-matplotlib software-properties-common wget libfreetype6-dev libpng-dev pkg-config python-dev
sudo pip install -U distribute
sudo pip install docopt git+git://github.com/bmcfee/librosa.git joblib
cd /usr/local/src ; sudo wget --no-check-certificate http://www.mega-nerd.com/SRC/libsamplerate-0.1.8.tar.gz ; sudo tar xvfz libsamplerate-0.1.8.tar.gz ; cd libsamplerate-0.1.8 && sudo ./configure && sudo make && sudo make install
sudo pip install scikits.samplerate
cd /usr/local/src ; sudo git clone https://github.com/dpwe/audfprint.git
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
cd /var/www ; sudo git clone https://github.com/slifty/tvarchive-fingerprinting.git
sudo chown -R $(whoami):www-data /var/www/tvarchive-fingerprinting/
cd /var/www/tvarchive-fingerprinting ; composer install
printf "<VirtualHost *:80>\n\tDocumentRoot /var/www/tvarchive-fingerprinting/public\n\tErrorLog \${APACHE_LOG_DIR}/error.log\n\tCustomLog \${APACHE_LOG_DIR}/access.log combined\n</VirtualHost>" | sudo tee /etc/apache2/sites-available/tvarchive-fingerprinting.conf
sudo chown -R $(whoami):www-data /var/www/tvarchive-fingerprinting/
sudo apt-get install supervisor
sudo cp /var/www/tvarchive-fingerprinting/fingerprinting-worker.conf.example /etc/supervisor/conf.d
sudo a2dissite 000-default
sudo a2ensite tvarchive-fingerprinting
sudo a2enmod rewrite
sudo apachectl restart
sudo -upostgres createdb tvarchive_fingerprinting
sudo -upostgres createuser tvarchive_fingerprinting -P
sudo -upostgres psql -c "GRANT ALL PRIVILEGES ON DATABASE tvarchive_fingerprinting to tvarchive_fingerprinting"
sudo chmod 777 -R /var/www/tvarchive-fingerprinting/storage/audfprint
sudo chmod 777 -R /var/www/tvarchive-fingerprinting/storage/logs

```

Next you have to update your apache config to `AllowOverride all` in the `/var/www` directory.

```shell
sudo vi /etc/apache2/apache2.conf
```

Next you have to set up your .env as appropriate.

```shell
cp /var/www/tvarchive-fingerprinting/.env.example /var/www/tvarchive-fingerprinting/.env
php /var/www/tvarchive-fingerprinting/artisan key:generate
vi /var/www/tvarchive-fingerprinting/.env
```

Some key values:

```
AUDFPRINT_PATH=/usr/local/src/audfprint/audfprint.py
DB_DATABASE=tvarchive_fingerprinting
DB_USERNAME=tvarchive_fingerprinting
FFMPEG_BINARY_PATH=/usr/local/bin/ffmpeg
FFMPEG_BINARY_PATH=/usr/local/bin/ffprobe
FPRINT_STORE=/var/www/tvarchive-fingerprinting/storage/audfprint/
```

Now migrate...

```shell
php /var/www/tvarchive-fingerprinting/artisan migrate:refresh
```

Finally, set up your supervisor processes
