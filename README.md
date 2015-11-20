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

## Installing

(for now I'll just throw various commands in no particular order for reference, lulz.  Eventually this will be turned into real instructions)

- docker pull slifty/audfprint
