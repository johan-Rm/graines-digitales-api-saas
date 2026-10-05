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
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec api composer install
docker compose -f docker-compose.dev.yml exec api php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose -f docker-compose.dev.yml exec api php bin/console doctrine:migrations:migrate --no-interaction
```

API locale : `http://api.localhost:18080` (vhost Docker Nginx).
`http://localhost:18080` reste accessible.

## Auth

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/local-users` en local/test uniquement
- `POST /api/projects/{projectId}/members` pour creer ou associer un user a un projet

Les routes `/api/projects/{projectId}/...` exigent un JWT valide et un membership projet.

Le dashboard peut sauvegarder un bien existant par identifiant metier quand il ne connait
pas encore l'UUID Doctrine :

```txt
PUT /api/dashboard/accommodations/{identifier}?locale=fr
Content-Type: application/ld+json
```

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
Si `caption` est absent ou vide, le backend le genere depuis le nom du fichier en
remplacant les separateurs (`-`, `_`, `.`) par des espaces et en normalisant la casse.

## Traductions

Les contenus editoriaux utilisent un modele local inspire de Sylius :
une ressource racine porte le projet, l'audit et les statuts, et une table
`*Translation` porte les champs localises par `locale`.

Voir `docs/04-domain-model/translations.md` pour le modèle et les invariants, et `docs/03-api-contracts/translations.md` pour le contrat des routes.

## Tests

```bash
docker compose -f docker-compose.dev.yml exec api php bin/phpunit
```

Pendant une intervention IA ou une petite correction locale, preferer les tests cibles
lies au changement. La suite PHPUnit complete est reservee aux validations pre-merge et
aux merges vers `develop`, sauf demande explicite.

Validation backend complete avant merge vers `develop` :

```bash
docker compose -f docker-compose.dev.yml exec api php bin/console lint:container
docker compose -f docker-compose.dev.yml exec api php bin/console doctrine:schema:validate
docker compose -f docker-compose.dev.yml exec api php bin/phpunit
```

Les tests critiques couvrent auth, refresh token, isolation projet, acces inter-projets,
traductions, upload image et soft delete.

## Collection Insomnia

`Insomnia_2026-10-04.yaml` fournit une collection de requetes. Les mots de passe
des environnements exportes sont volontairement vides : renseigner les valeurs
localement apres import. Pour Showcase local, utiliser le compte
`demo@graines-digitales.online` et le mot de passe passe a Make. Les requetes
de creation historiques sont des exemples a adapter au projet cible.

## Depots Git

Le depot d'organisation est le remote `origin`. Le depot de partage public
est le remote `public` :

```bash
git remote add public https://github.com/johan-Rm/graines-digitales-api-saas.git
git push origin develop master
git push public develop master
```

Les branches d'integration et de production sont publiees sur les deux depots
apres validation. Ne pas pousser les fichiers d'environnement, cles JWT,
medias ni exports contenant des credentials.
