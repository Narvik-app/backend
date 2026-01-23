# Narvik - Back

# Prérequis
- Base de données PostgreSQL avec l'extension `unaccent` activée.

## Fonctionnalités
- Gestion de la présence des membres
- Import manuel des membres
- Import des présences

[Plus d'informations](https://docs.narvik.app/)
## Roadmaps
- [x] Export des données en csv

## Documentations

0. [Développement](docs/0.README.md)

## Démarrage rapide
Un [Makefile](Makefile) est présent afin de faciliter le développement.

### Avec Docker
- `make up` pour démarrer le projet
- `make build` pour construire les images

### Avec Podman/Buildah
Ce projet supporte également Podman et Buildah comme alternative à Docker:

- `make buildah-build` pour construire l'image de développement avec Buildah
- `make buildah-build-prod` pour construire l'image de production avec Buildah  
- `make podman-up` pour démarrer avec podman-compose
- `make podman-start` pour construire et démarrer avec Podman/Buildah

Le fichier `Containerfile` est un lien symbolique vers `Dockerfile` pour la compatibilité avec Buildah/Podman.

### Builds multi-plateformes (amd64 + arm64)
Les images peuvent être construites pour plusieurs architectures:

#### Avec Docker Buildx:
- `make build-multiplatform` pour construire et pousser les images multi-plateformes
- `make build-multiplatform-local` pour construire localement sans pousser

#### Avec Buildah:
- `make buildah-build-multiplatform` pour construire les images multi-plateformes avec Buildah

## License

GNU AGPLv3 Licence.

## Crédits
Crée par Benoît VIGNAL
