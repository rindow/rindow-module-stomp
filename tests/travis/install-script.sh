#!/bin/sh

composer install

case "$TRAVIS_PHP_VERSION" in
	7.* ) composer require --dev "phpunit/phpunit 6.*" ;;
esac