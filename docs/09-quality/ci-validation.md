# Validation CI

Commandes de reference :

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

La CI doit executer l'equivalent dans un environnement reproductible.
