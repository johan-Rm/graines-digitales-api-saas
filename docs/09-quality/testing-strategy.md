# Strategie de tests

Tests critiques couverts par la suite fonctionnelle API Platform :

- refus sans JWT
- login JWT valide
- refresh token valide
- refresh token invalide refuse
- utilisateur membre autorise
- utilisateur non membre refuse
- isolation inter-projets
- lecture localisee
- fallback de traduction explicite
- upload image
- rejet des fichiers non image
- soft delete
- coherence des fixtures d'hebergements et de leurs relations metier

Les tests ne doivent pas dependre de donnees inserees manuellement.

## Execution locale

```bash
docker compose exec -e APP_ENV=test -e DATABASE_URL=postgresql://graines_digitales_api:graines_digitales_api@postgres:5432/graines_digitales_api_test?serverVersion=17\&charset=utf8 api php bin/console doctrine:database:create --if-not-exists
docker compose exec api php bin/phpunit
```

Validation backend complete avant merge :

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

PHPUnit force `APP_ENV=test` et `APP_DEBUG=0` pour garder une sortie exploitable et
eviter les logs debug Symfony pendant les validations locales et CI. Le logger de
l'environnement `test` est un `NullLogger` : les tests doivent verifier les erreurs via les
assertions HTTP et PHPUnit, pas via la sortie standard.

Le bootstrap PHPUnit cree automatiquement la base PostgreSQL de test si elle n'existe pas
encore. Cette creation est limitee a `APP_ENV=test` et a une base dont le nom se termine par
`_test`.

## Isolation des tests fonctionnels

`FunctionalTestCase` prepare la base de test une fois par classe : schema Doctrine recree,
puis fixtures demo chargees. Cette strategie reduit fortement le cout de la suite complete
tout en conservant une base fraiche entre classes.

Les classes qui mutent les memes fixtures dans plusieurs methodes doivent surcharger
`shouldResetDatabaseBeforeEachTest()` et retourner `true`. C'est obligatoire pour les tests
qui archivent ou modifient une ressource fixture critique reutilisee par une autre methode de
la meme classe.

## Limites connues

La suite fonctionnelle reste plus couteuse qu'une strategie transactionnelle globale ou
qu'un snapshot SQL restaure a chaque test. Ces options restent possibles si le volume de
tests augmente encore, mais la V1 actuelle evite une dependance supplementaire et conserve
un comportement lisible.

La creation d'une traduction manquante via `PUT` n'est pas couverte ici : le test de
lecture localisee cree explicitement une traduction en base pour verifier le contrat de
lecture et de fallback sans elargir le scope de TASK-005.
