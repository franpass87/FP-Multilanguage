# Test Coverage

Automatic coverage reports are generated with PHPUnit when either Xdebug or PCOV is available.

## Generate locally

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html docs/coverage/html
```

If neither Xdebug nor PCOV is installed, the command will emit `No code coverage driver available` and the `docs/coverage/html` directory will remain empty. Install one of the extensions before publishing coverage artifacts.
