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
3. Install [Composer](https://getcomposer.org/)
4. Clone the audfprint docker image and make sure it runs

	```shell
	docker pull slifty/audfprint
	docker run slifty/audfprint
	```

5. Clone this repository into a living breathing live php enabled directory mod rewrite should be enabled

6. Install composer dependencies

	```shell
		cd /path/to/your/clone/here
		composer install
	```

7. Copy .env.example to .env in the root directory, then edit it

	```shell
		cd /path/to/your/clone/here
		cp .env.example .env
		vi .env
	```

	* RSYNC_IDENTITY_FILE: a path to a private key that web root has 500 access to, with any media files you plan on importing
	* FPRINT_STORE: a path to the /storage/audfprint director inside of the repository
	* DOCKER_HOST: the location and port of the docker you set up for audfprint
	*

8. Install supervisor and [enable the job queue](http://laravel.com/docs/5.1/queues#running-the-queue-listener)

(for now I'll just throw various commands in no particular order for reference, lulz.  Eventually this will be turned into real instructions)

- docker pull slifty/audfprint
