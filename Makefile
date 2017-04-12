.PHONY: lint phpcs phpcbf

test: lint phpcs

lint:
	find . -name '*.php' -type f -print0 | xargs -0 -n 1 -P 4 php -nl -d display_errors=stderr > /dev/null

phpcs:
	test -d /tmp/phpcs || git clone -b master --depth 1 https://github.com/squizlabs/PHP_CodeSniffer.git /tmp/phpcs
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	/tmp/phpcs/scripts/phpcs --config-set installed_paths /tmp/wpcs
	/tmp/phpcs/scripts/phpcs -p . --standard=WordPress-VIP --extensions=php --runtime-set ignore_warnings_on_exit true

phpcbf:
	test -d /tmp/phpcs || git clone -b master --depth 1 https://github.com/squizlabs/PHP_CodeSniffer.git /tmp/phpcs
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	/tmp/phpcs/scripts/phpcs --config-set installed_paths /tmp/wpcs
	/tmp/phpcs/scripts/phpcbf -p . --standard=WordPress-VIP --extensions=php

clean:
	rm -r /tmp/phpcs /tmp/wpcs
