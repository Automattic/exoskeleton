.PHONY: lint phpcs phpcbf

test: lint phpcs

lint:
	find . -name '*.php' -type f -print0 | xargs -0 -n 1 -P 4 php -nl -d display_errors=stderr > /dev/null

phpcs:
	test -f /tmp/phpcs || curl -L https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar -o /tmp/phpcs && chmod +x /tmp/phpcs
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	/tmp/phpcs --config-set installed_paths /tmp/wpcs
	/tmp/phpcs -p . --standard=WordPress-VIP --extensions=php --runtime-set ignore_warnings_on_exit true

phpcbf:
	test -f /tmp/phpcs || curl -L https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar -o /tmp/phpcs && chmod +x /tmp/phpcs
	test -f /tmp/phpcbf || curl -L https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar -o /tmp/phpcbf && chmod +x /tmp/phpcbf
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	/tmp/phpcs --config-set installed_paths /tmp/wpcs
	/tmp/phpcbf -p . --standard=WordPress-VIP --extensions=php

clean:
	rm -rf /tmp/phpcs /tmp/phpcbf /tmp/wpcs
