# Symfony 8 Content Management API

Backend SaaS multi-clients et multi-projets pour centraliser les contenus des webapps Graines Digitales.

## Stack

- PHP 8.4
- Symfony 8
- API Platform
- PostgreSQL
- Doctrine ORM
- LexikJWTAuthenticationBundle
- Gesdinet JWTRefreshTokenBundle
- VichUploaderBundle
- Docker Compose

## Documentation

Le point d'entree de la documentation API est `docs/index.md`.
Il definit l'ordre de lecture et le proprietaire documentaire de chaque regle.

## Demarrage local

```bash
cp .env.example .env.local
docker compose up -d --build
docker compose exec api composer install
docker compose exec api php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

API locale : `http://localhost:18080`

## Auth

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/local-users` en local/test uniquement

Les routes `/api/projects/{projectId}/...` exigent un JWT valide et un membership projet.

## Uploads

Les images sont uploadees via :

```txt
POST /api/projects/{projectId}/media-objects
Content-Type: multipart/form-data
```

Le backend calcule `checksumSha256` cote serveur.

## Traductions

Les contenus editoriaux utilisent un modele local inspire de Sylius :
une ressource racine porte le projet, l'audit et les statuts, et une table
`*Translation` porte les champs localises par `locale`.

Voir `docs/TRANSLATIONS.md` pour le contrat des routes et les invariants.

## Tests

```bash
docker compose exec api php bin/phpunit
```

Les tests critiques doivent couvrir auth, refresh token, isolation projet, acces inter-projets aux medias, upload image et soft delete.
