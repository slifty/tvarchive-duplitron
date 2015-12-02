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

