# Vault
[![Docker Hub Link](https://img.shields.io/docker/image-size/medleybox/vault/latest?style=for-the-badge)][dockerhub-vault]
[![Docker Hub Link](https://img.shields.io/docker/cloud/automated/medleybox/vault?style=for-the-badge)][dockerhub-vault-builds]

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

## Bin Scripts
Bin scripts have been written to automate common CLI tasks:

| Script | Description |
|--|--|
| bin/docker-build | Build docker images via docker-compose |
| bin/docker-console | Run the Symfony Console within the vault container |
| bin/docker-entrypoint | Docker image entrypoint for vault. Starts FPM and then Nginx |
| bin/docker-psql | Login to the pqsl console |
| bin/docker-up | Start the stack locally via docker-compose  |

[github-import]: https://github.com/medleybox/import
[dockerhub-vault]: https://hub.docker.com/repository/docker/medleybox/vault
[dockerhub-vault-builds]: https://hub.docker.com/repository/docker/medleybox/vault/builds
