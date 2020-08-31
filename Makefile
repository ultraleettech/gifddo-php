help:
	@echo "Please use \`make <target>' where <target> is one of"
	@echo "  test                           to perform unit tests.  Provide TEST to perform a specific test."
	@echo "  coverage                       to perform unit tests with code coverage. Provide TEST to perform a specific test."
	@echo "  coverage-show                  to show the code coverage report"
	@echo "  clean                          to remove build artifacts"
	@echo "  static                         to run phpstan and php-cs-fixer on the codebase"
	@echo "  static-phpstan                 to run phpstan on the codebase"
	@echo "  static-phpstan-update-baseline to regenerate the phpstan baseline file"
	@echo "  static-codestyle-fix           to run php-cs-fixer on the codebase, writing the changes"
	@echo "  static-codestyle-check         to run php-cs-fixer on the codebase"

test:
	bash vendor/bin/phpunit

coverage:
	bash vendor/bin/phpunit --coverage-html=build/artifacts/coverage

coverage-show: view-coverage

view-coverage:
	open build/artifacts/coverage/index.html

clean:
	rm -rf artifacts/*

static: static-phpstan static-psalm static-codestyle-check

static-psalm:
	docker run --rm -it -v ${PWD}:/app -w /app vimeo/psalm-github-actions

static-phpstan:
	docker run --rm -it -e REQUIRE_DEV=true -v ${PWD}:/app -w /app oskarstark/phpstan-ga:0.12.31 analyze $(PHPSTAN_PARAMS)

static-phpstan-update-baseline:
	$(MAKE) static-phpstan PHPSTAN_PARAMS="--generate-baseline"

static-codestyle-fix:
	docker run --rm -it -v ${PWD}:/app -w /app oskarstark/php-cs-fixer-ga:2.16.3.1 --diff-format udiff $(CS_PARAMS)

static-codestyle-check:
	$(MAKE) static-codestyle-fix CS_PARAMS="--dry-run"
