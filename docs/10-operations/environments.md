# Environnements

Environnements connus :

- local via Docker Compose
- test via PHPUnit/Symfony test env
- production via `api.graines-digitales.online`, routee par le repo `vps-edge`

Les variables sensibles doivent venir de l'environnement, pas du depot.

## Production

L'environnement production doit fournir :

- `APP_ENV=prod`
- `APP_DEBUG=0`
- `DEFAULT_URI=https://api.graines-digitales.online`
- `DATABASE_URL` vers PostgreSQL
- `CORS_ALLOW_ORIGIN` limite aux frontends autorises
- `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`
- `MESSENGER_TRANSPORT_DSN`
- `PLATFORM_SUPER_ADMIN_EMAILS`

Le host public et TLS sont geres hors de ce repo par `vps-edge`.

## Vhost Docker local

Le Nginx du Compose dev accepte `localhost`, `api.localhost` et
`api.graines-digitales.local`. Ce dernier alias necessite une resolution
DNS ou une entree hosts sur la machine du client vers `127.0.0.1`.
URL API : `http://api.localhost:18080` ; documentation :
`http://api.localhost:18080/api/docs`. Dans Insomnia, definir
`base_url` a `http://api.localhost:18080`. Le frontend utilise
`http://webapp.localhost:8080`. Aucun reverse proxy partage n'est configure.

Pour les appels depuis un autre container, utiliser le nom de service
Docker sur un reseau commun ou `host.docker.internal:18080` avec
`host-gateway` : `api.localhost` designe la boucle locale du client.
