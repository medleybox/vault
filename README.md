# Vault

A Symfony 6 application to retrieve and store music for Medlybox. Rewritten concepts from [medlybox/import][github-import].

[![php-composer-and-tests](https://github.com/medleybox/vault/actions/workflows/php.yml/badge.svg)][github-workflows-tests]
[![docker-publish](https://github.com/medleybox/vault/actions/workflows/docker-publish.yml/badge.svg)][github-workflows-publish]

## Quick Start
This project uses Docker and docker-compose to manage project dependencies. You will need up to date installed and working versions of both. PHP 8.2 is used within the nginx + FPM container setup. There is only one container running fpm for the vault service. Use the nginx image build to Medleybox

```bash
# Install PHP dependencies on to host
composer install

# Build docker images
bin/docker-build

# Start docker containers
bin/docker-up
```

Once the containers have started within the stack, you will have access to the Symfony Console via the provided bin script `bin/docker-console`.

## Bin Scripts
Bin scripts have been written to automate common CLI tasks:

| Script | Description |
|--|--|
| bin/docker-build | Build docker images via docker-compose |
| bin/docker-console | Run the Symfony Console within the vault container |
| bin/docker-entrypoint | Docker image entrypoint for vault. Starts FPM and then Nginx |
| bin/docker-up | Start the stack locally via docker-compose |
| bin/run-tests | Run PHP CS tests using phpstan and phpcs |


## Testing
PHP Coding Standards tests using `phpstan` and `squizlabs/php_codesniffer` using the [Symfony:risky][phpcs-symfony-ruleset] ruleset. Use the `run-tests` bin script to use the correct command line arguments for each program.

```bash
docker-compose exec vault bin/run-tests 
```

Fix reported issues with `phpcbf`:
```bash
vendor/bin/phpcbf --standard=PSR12 --colors src
```

## API Endpoint tests

### Import
```
curl -d "uuid=dQw4w9WgXcQ" -X POST http://localhost:8084/entry/import
```

## License
GNU General Public License v3.0 or later

See [LICENCE](LICENCE) to see the full text.

[github-import]: https://github.com/medleybox/import
[github-workflows-tests]: https://github.com/medleybox/vault/actions/workflows/php.yml
[github-workflows-publish]: https://github.com/medleybox/vault/actions/workflows/docker-publish.yml
[phpcs-symfony-ruleset]: https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/2.17/doc/ruleSets/SymfonyRisky.rst
