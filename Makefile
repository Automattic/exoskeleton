.PHONY: init lint phpcs phpcbf clean

test: lint phpcs

init:
	test -d /tmp/phpcs || git clone -b master --depth 1 https://github.com/squizlabs/PHP_CodeSniffer.git /tmp/phpcs
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	/tmp/phpcs/bin/phpcs --config-set installed_paths /tmp/wpcs

lint:
	find . -name '*.php' -type f -print0 | xargs -0 -n 1 php -nl

phpcs: init
	/tmp/phpcs/bin/phpcs -p . --standard=WordPress-VIP --extensions=php --runtime-set ignore_warnings_on_exit true

phpcbf: init
	/tmp/phpcs/bin/phpcbf -p . --standard=WordPress-VIP --extensions=php

clean:
	rm -rf /tmp/phpcs /tmp/wpcs
