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
- `POST /api/projects/{projectId}/members` pour creer ou associer un user a un projet

Les routes `/api/projects/{projectId}/...` exigent un JWT valide et un membership projet.

Exemple de creation d'un user directement associe a un projet :

```json
{
  "email": "user@example.test",
  "password": "password123",
  "projectRole": "owner",
  "roles": ["ROLE_ADMIN"]
}
```

Les slugs sont generes automatiquement depuis `name` ou le titre principal quand le champ
`slug` est absent. Si un slug est fourni, le backend le normalise.

## Uploads

Les images sont uploadees via :

```txt
POST /api/projects/{projectId}/media-objects
Content-Type: multipart/form-data
```

L'upload bulk est disponible via :

```txt
POST /api/projects/{projectId}/media-objects/bulk
Content-Type: multipart/form-data
files[]: image/jpeg|image/png|image/webp|image/gif|image/avif
```

Limites MVP : 20 images par requete, 12M par image, `post_max_size=256M` dans
l'image Docker PHP.

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

Validation backend complete avant merge :

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

Les tests critiques couvrent auth, refresh token, isolation projet, acces inter-projets,
traductions, upload image et soft delete.
