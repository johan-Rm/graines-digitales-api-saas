# Task : Generation automatique des slugs

## Metadonnees

- ID : TASK-011
- Source : besoin utilisateur du 2026-05-21
- Statut : Terminee
- Perimetre : Organization, Project, contenus traduits, tests API
- Dependances fonctionnelles : TASK-001, TASK-002

## Objectif

Eviter aux clients API de renseigner manuellement les slugs. Le backend doit generer un
slug stable et lisible quand une ressource porte un titre ou un nom suffisant.

## Contrat MVP

- `Organization.slug` est genere depuis `name` si le slug est absent.
- `Project.slug` est genere depuis `name` si le slug est absent.
- Les contenus traduits generent leur slug depuis leur champ principal si le slug est absent :
  - `ArticleTranslation.headline`
  - `WebPageTranslation.headline`
  - `AccommodationTranslation.name`
- Si un slug est envoye explicitement, il est normalise mais pas remplace.
- Le backend reste responsable de la generation, quel que soit le client API.

## Plan d'execution

- [x] Ajouter un helper local de normalisation slug.
- [x] Brancher Organization et Project.
- [x] Brancher les traductions editoriales.
- [x] Ajouter des tests fonctionnels de creation sans slug.
- [x] Documenter le comportement.

## Validations attendues

- [x] `docker compose exec api php bin/phpunit` passe.
- [x] `docker compose exec api php bin/console lint:container` passe.
