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
