# Task : Optimisation de la suite de tests fonctionnels

## Metadonnees

- ID : TASK-020
- Source : retour execution locale du 2026-05-25
- Statut : Fait
- Perimetre : PHPUnit, tests fonctionnels API Platform, base de test PostgreSQL, logs test
- Dependances fonctionnelles : TASK-005

## Objectif

Reduire fortement le temps d'execution de la suite PHPUnit sans affaiblir les garanties
critiques du projet : isolation inter-projets, reproductibilite des fixtures et absence de
dependance a des donnees locales manuelles.

Le diagnostic initial etait que `FunctionalTestCase::setUp()` recreait tout le schema
Doctrine et rechargeait les fixtures demo avant chaque test fonctionnel. Cette strategie
etait simple et fiable, mais elle rendait la suite complete lente a mesure que le nombre de
scenarios augmentait.

Reference locale mesuree avant optimisation :

- suite complete : environ `15:26`
- sortie PHPUnit polluee par les logs debug Symfony et les logs d'exceptions attendues

Resultat apres optimisation :

- suite complete : environ `3:05`
- sortie PHPUnit silencieuse hors progression et erreurs PHPUnit

## Perimetre MVP

- Mesurer le temps de reference de la suite complete et des classes fonctionnelles les plus
  lentes.
- Reduire le bruit des logs en environnement `test`.
- Remplacer le reset schema + fixtures par test par une strategie plus rapide.
- Conserver une isolation forte entre tests pour eviter les fuites d'etat.
- Documenter la nouvelle strategie de test et ses limites.

## Pistes techniques a evaluer

1. Transactions avec rollback autour de chaque test fonctionnel.
2. Snapshot SQL de base prechargee puis restauration rapide entre tests ou classes.
3. Reset schema + fixtures une seule fois par classe lorsque les tests ne mutent pas le
   meme etat critique.
4. Suites PHPUnit separees : `unit`, `functional-critical`, `functional-full`.

La solution retenue doit rester compatible avec Docker et CI.

Solution retenue en V1 : reset schema + fixtures une seule fois par classe de tests, avec
retour explicite au reset par test pour les classes qui mutent les memes fixtures critiques.
Les classes `DashboardAccommodationSaveTest` et `ProjectIsolationTest` conservent un reset
strict par test.

Le bootstrap PHPUnit cree aussi la base PostgreSQL de test manquante avant le reset de
schema, uniquement en `APP_ENV=test` et uniquement pour une base suffixee `_test`.

## Contraintes

- Ne pas reduire la couverture des tests d'isolation inter-projets.
- Ne pas rendre les tests dependants de l'ordre d'execution.
- Ne pas masquer les erreurs Doctrine ou API Platform utiles au debug.
- Ne pas introduire de dependance lourde si une solution Symfony/Doctrine standard suffit.
- La base `graines_digitales_api_test` doit pouvoir etre creee automatiquement ou documentee
  clairement pour un setup local reproductible.

## Plan d'execution

- [x] Mesurer le temps actuel de `docker compose exec api php bin/phpunit`.
- [x] Identifier les classes les plus couteuses.
- [x] Desactiver ou router les logs debug verbeux en environnement `test`.
- [x] Prototyper une strategie d'isolation plus rapide.
- [x] Verifier que les tests restent independants via relance complete.
- [x] Mettre a jour `docs/09-quality/testing-strategy.md`.
- [x] Ajouter les commandes utiles dans la documentation de validation.

## Validations attendues

- [x] `docker compose exec api php bin/phpunit` passe.
- [x] `docker compose exec api php bin/console lint:container` passe.
- [x] `docker compose exec -e APP_ENV=test -e DATABASE_URL=... api php bin/console doctrine:schema:validate` passe.
- [x] La suite complete est significativement plus rapide que la reference mesuree.
- [x] Les tests critiques d'isolation inter-projets restent couverts.

Note : `doctrine:schema:validate` sans `DATABASE_URL` explicite signale une base dev locale
non migree (`area_size`, `area_terrace`, `floor_size` attendus en `VARCHAR(80)`). Ce point
est lie a la migration `Version20260526143000`, hors scope de TASK-020.

## Points de vigilance

- Une strategie transactionnelle peut etre insuffisante si certains tests ferment la
  connexion Doctrine, declenchent des commits implicites ou passent par des processus
  separes.
- Un snapshot SQL doit rester synchronise avec les fixtures et le schema Doctrine.
- Un reset par classe peut accelerer fortement, mais seulement si les tests d'une meme
  classe ne se polluent pas mutuellement.
