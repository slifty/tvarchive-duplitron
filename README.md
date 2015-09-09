# Repeated Content Detection

This repository uses [audfprint](https://github.com/dpwe/audfprint) (via a docker image) to process an mp3 file and identify segments of repeated content.

## Dependencies

To run this code you need:

* PHP 5.*
* [Composer](https://getcomposer.org/) for dependency management
* [Docker](https://www.docker.com/) for running audfprint
* [ffmpeg](https://ffmpeg.org/) for manipulating audio files



## Dependencies

1. Install all required software (see the Dependencies section)

2. Install libraries with composer

	If you installed composer globally:

	```shell
	$> composer install
	```

3. Create a local configuration file

	```shell
	$> cp config/phpVideoToolkit.example config/phpVideoToolkit.php
	```
* 