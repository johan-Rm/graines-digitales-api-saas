# Implementation Notes

## Decisions

- Symfony 8 is the target runtime.
- The API is dockerized and does not rely on the host PHP version.
- JWT MVP uses `lexik/jwt-authentication-bundle`.
- Refresh token MVP uses `gesdinet/jwt-refresh-token-bundle`; keep it replaceable and test rotation/revocation.
- Upload MVP uses `vich/uploader-bundle` through `MediaObject`.
- Project isolation is enforced in state providers/processors before Doctrine persistence.
- Soft delete is implemented as archival through `deletedAt`; physical purge is out of MVP.

## Known setup constraint

The current host PHP is 7.4, so Composer install and Symfony commands must run in Docker.

```bash
docker compose up -d --build
docker compose exec api composer install
docker compose exec api php bin/console doctrine:migrations:diff
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec api php bin/phpunit
```

## Follow-up before merge

- Generate Doctrine migrations from the mapped entities once dependencies are installed.
- Replace incomplete functional tests with fixture-backed scenarios.
- Verify exact Symfony 8 compatible versions selected by Composer.
