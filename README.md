# Vault
A Symfony 5 application to retrieve and store music for Medlybox. Rewritten concepts from  [medlybox/import][github-import].

## Quick Start
This project uses Docker and docker-compose to manage project dependencies. You will need up to date installed and working versions of both. PHP 7.4 is used within the nginx + FPM container setup. There is only one container for running nginx and fpm.

```bash
# Install PHP dependencies on to host
composer install

# Build docker images
bin/docker-build

# Start docker containers
bin/docker-up
```

Once the containers have started within the stack, you will have access to the Symfony Console via the provided bin script `bin/docker-console`.

[github-import]: https://github.com/medleybox/import
