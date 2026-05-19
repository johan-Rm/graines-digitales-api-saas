# Task : Mise en place des tests backend critiques

## Metadonnees

- ID : TASK-005
- Source : ajustement utilisateur du 2026-05-19
- Statut : A faire
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

- [ ] Stabiliser la configuration PHPUnit.
- [ ] Ajouter ou finaliser les fixtures de test.
- [ ] Couvrir l'authentification JWT.
- [ ] Couvrir les refresh tokens.
- [ ] Couvrir l'isolation projet.
- [ ] Couvrir les traductions.
- [ ] Couvrir les uploads image.
- [ ] Couvrir le soft delete.
- [ ] Ajouter les commandes de validation dans le README.
- [ ] Documenter les limites connues.

## Validations attendues

- [ ] `docker compose exec api php bin/phpunit` passe sans test incomplet.
- [ ] `docker compose exec api php bin/console lint:container` passe.
- [ ] `docker compose exec api php bin/console doctrine:schema:validate` passe.
- [ ] Les tests couvrent les regressions critiques avant merge.
