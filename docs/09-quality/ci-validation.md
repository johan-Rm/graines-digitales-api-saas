# Validation CI

Pendant les interventions IA et les petites corrections locales, lancer seulement les
validations ciblees utiles au changement. La suite PHPUnit complete ne doit pas etre
declenchee automatiquement a chaque sollicitation IA.

Commandes de reference :

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

La CI doit executer l'equivalent dans un environnement reproductible uniquement pour les
validations pre-merge et les merges vers `develop`. Les branches de travail peuvent se
limiter a lint, validation de conteneur, schema cible ou tests PHPUnit cibles selon le
scope du changement.
