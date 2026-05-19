# Migrations

Commandes locales :

```bash
docker compose exec api php bin/console doctrine:migrations:diff
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec api php bin/console doctrine:schema:validate
```

Regles :

- Toute evolution de schema passe par une migration.
- Les migrations doivent passer depuis une base vide.
- Les migrations qui deplacent des donnees doivent copier les donnees avant de
  supprimer les colonnes source.
