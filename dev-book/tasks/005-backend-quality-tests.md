# Task : Mise en place des tests backend critiques

## Metadonnees

- ID : TASK-005
- Source : ajustement utilisateur du 2026-05-19
- Statut : Terminee
- Perimetre : PHPUnit, tests fonctionnels API Platform, securite et regressions
- Dependances fonctionnelles : TASK-001, TASK-002, TASK-003, TASK-004

## Objectif

Mettre en place une suite de tests backend couvrant les contrats critiques du SaaS :
authentification, refresh token, isolation projet, traductions, upload image et filtrage des
contenus archives.

## Perimetre MVP

- Tests fonctionnels API Platform.
- Tests unitaires ou integration legers pour services applicatifs.
- Fixtures dediees aux tests.
- Execution reproductible dans Docker.

## Scenarios prioritaires

- refus sans JWT sur routes projet
- login JWT valide
- refresh token valide
- refresh token expire ou invalide refuse
- utilisateur membre d'un projet autorise
- utilisateur non membre refuse
- absence de fuite de contenus entre deux projets
- lecture localisee d'un contenu traduit
- fallback de traduction explicite
- upload image valide
- upload fichier non image refuse
- contenu `deletedAt` absent des collections standards

## Contraintes

- Les tests ne doivent pas dependre de donnees manuelles locales.
- Les tests doivent pouvoir tourner en CI.
- Les tests doivent rester lisibles et centres sur les invariants metier.
- Les tests incomplets existants doivent etre remplaces par de vrais scenarios.

## Plan d'execution

- [x] Stabiliser la configuration PHPUnit.
- [x] Ajouter ou finaliser les fixtures de test.
- [x] Couvrir l'authentification JWT.
- [x] Couvrir les refresh tokens.
- [x] Couvrir l'isolation projet.
- [x] Couvrir les traductions.
- [x] Couvrir les uploads image.
- [x] Couvrir le soft delete.
- [x] Ajouter les commandes de validation dans le README.
- [x] Documenter les limites connues.

## Validations attendues

- [x] `docker compose exec api php bin/phpunit` passe sans test incomplet.
- [x] `docker compose exec api php bin/console lint:container` passe.
- [x] `docker compose exec api php bin/console doctrine:schema:validate` passe.
- [x] Les tests couvrent les regressions critiques avant merge.

## Limites connues

- La suite fonctionnelle recree le schema et recharge les fixtures de demo avant chaque
  test. C'est volontaire pour garantir la reproductibilite MVP, mais ce sera a optimiser
  si le temps d'execution devient bloquant.
- La creation d'une traduction manquante via `PUT` reste hors perimetre de cette tache ;
  les tests TASK-005 valident la lecture localisee, le fallback et l'isolation projet.
