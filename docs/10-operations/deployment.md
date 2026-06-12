# Deploiement

Ce runbook prepare le deploiement de l'API sur :

```txt
https://api.graines-digitales.online
```

Le reverse proxy, TLS, routage public et publication du host sont portes par le repo
`vps-edge`. Ce repo API reste responsable de l'image applicative, des variables
runtime, des migrations, des cles JWT, du stockage media et des workers Messenger.

## Responsabilites `vps-edge`

`vps-edge` doit fournir :

- le host public `api.graines-digitales.online`
- le certificat TLS et la redirection HTTP vers HTTPS
- le routage FastCGI vers le service PHP-FPM de l'API
- les headers proxy standards (`X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-For`)
- un reseau Docker partage si l'API est exposee par container
- les secrets de production via l'environnement cible, jamais via le depot

Point de vigilance securite : le firewall Symfony extrait le JWT de la requete HTTP.
Le reverse proxy ne doit pas retirer ni reecrire le header `Authorization`.

Avec `docker-compose.prod.yml`, `vps-edge` doit cibler le service `api` sur le port
FastCGI interne `9000`, via le reseau externe configure par `VPS_EDGE_NETWORK`.
Le service PHP-FPM ne parle pas HTTP : un simple reverse proxy HTTP vers `api:9000`
ne fonctionnera pas.

## Variables production requises

Valeurs minimales attendues :

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=...
DEFAULT_URI=https://api.graines-digitales.online

DATABASE_URL=postgresql://...?...serverVersion=17&charset=utf8
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

CORS_ALLOW_ORIGIN=^https://(...domaines-front-autorises...)$

JWT_SECRET_KEY=/srv/api/config/jwt/private.pem
JWT_PUBLIC_KEY=/srv/api/config/jwt/public.pem
JWT_PASSPHRASE=...
JWT_TOKEN_TTL=900
JWT_REFRESH_TOKEN_TTL=2592000

PLATFORM_SUPER_ADMIN_EMAILS=...
```

`DEFAULT_URI` est obligatoire pour les URLs generees hors contexte HTTP, notamment
depuis les commandes CLI ou les traitements asynchrones.

`CORS_ALLOW_ORIGIN` doit lister uniquement les frontends autorises. Ne pas utiliser un
regex trop large en production.

## Build applicatif

L'image de production doit etre immutable :

- code copie dans l'image
- dependances installees avec `composer install --no-dev --prefer-dist`
- aucun fichier `.env`, `.env.local`, cle JWT locale, cache, log ou media embarque
- `APP_ENV=prod` et `APP_DEBUG=0` fournis au runtime

Le fichier `.dockerignore` protege le contexte de build contre les fichiers locaux
sensibles ou inutiles. Toute image produite par `vps-edge` doit respecter cette liste.

Build local de reference :

```bash
docker build -f Dockerfile.prod -t graines-digitales-api:prod .
```

`docker-compose.prod.yml` fournit une topologie de reference pour le VPS :

- `api` : PHP-FPM Symfony
- `worker` : consommation Messenger
- `migrate` : migration Doctrine, active uniquement avec le profil `ops`
- `seed-import` : import seed projet ponctuel, actif uniquement avec le profil `ops`
- `postgres` : PostgreSQL local au VPS si aucun service manage n'est utilise

Le fichier `.env.example` liste les variables attendues sans secret reel.

## Cles JWT

Les cles JWT de production doivent etre generees sur l'environnement cible :

```bash
php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

Ne pas reutiliser les cles locales et ne pas committer de fichier `config/jwt/*.pem`.
Le volume ou secret qui porte ces fichiers doit etre lisible par le process PHP-FPM.

Dans la topologie Compose de reference, le volume persistant est `jwt_keys`.

## Base de donnees et migrations

Avant mise en ligne :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Les migrations doivent rester compatibles avec un deploiement sans downtime. Toute
nouvelle table project-scoped doit indexer `project_id`, et toute regression
d'isolation inter-projets est bloquante.

## Stockage media

Les uploads ne doivent pas etre stockes dans l'image applicative.

Le reverse proxy ou le frontend peut servir les fichiers publics selon le
`mediaBaseUrl` configure sur chaque `Project`. Verifier avant mise en prod :

- racine persistante `/srv/webapps` montee dans le container API
- droits d'ecriture pour PHP-FPM
- sauvegarde ou retention du volume media
- coherence entre `Project.mediaPath` et `Project.mediaBaseUrl`

Dans la topologie Compose de reference, `/srv/webapps` est une racine technique fixe.
Le sous-chemin par projet vient de `Project.mediaPath` en base.

## Import seed projet

L'import seed est une operation ponctuelle. Le chemin seed hote n'est pas une variable
runtime de l'API ; il est fourni uniquement au moment de lancer le service `seed-import`.

Exemple :

```bash
make prod-seed-load PROJECT=mlk-modern-site-web PASSWORD='project-password'
```

Le service monte `SEED_PROJECT_HOST_DIR` en lecture seule dans le container a
`/srv/seed/webapp`, puis la commande lit :

```txt
/srv/seed/webapp/{projectSlug}
```

Si les images doivent etre synchronisees, lancer la commande explicitement :

```bash
make prod-seed-load PROJECT=mlk-modern-site-web PASSWORD='project-password' ONLY=metadata,media,web-pages,accommodations
```

## Workers Messenger

Le transport actuel est Doctrine :

```env
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

Prevoir au minimum un worker separe du process HTTP :

```bash
php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
```

Le superviseur du VPS doit redemarrer le worker en cas d'arret. Les handlers doivent
rester idempotents.

## Sequence de deploiement

1. Construire ou publier l'image API.
2. Deployer les variables et secrets de production.
3. Monter les volumes persistants : JWT, media, logs si externalises.
4. Demarrer PostgreSQL ou verifier le service manage.
5. Lancer les migrations.
6. Demarrer PHP-FPM/API et le worker Messenger.
7. Brancher `api.graines-digitales.online` dans `vps-edge`.
8. Verifier `POST /api/auth/login` puis une route project-scoped avec JWT.

## Validations pre-merge

Avant merge vers la branche d'integration :

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

Pour une mise en production, verifier aussi le contrat CORS, les secrets JWT, les
migrations et l'acces media.
