# Task : Creation de users directement associes a un projet

## Metadonnees

- ID : TASK-010
- Source : besoin utilisateur du 2026-05-21
- Statut : Terminee
- Perimetre : auth locale, membres projet, API projet
- Dependances fonctionnelles : TASK-001, TASK-006

## Objectif

Permettre de creer ou reutiliser un utilisateur et de l'associer directement a un projet
dans le meme appel API.

## Contrat MVP

- Route : `POST /api/projects/{projectId}/members`
- Payload :
  - `email` requis
  - `password` requis uniquement si l'utilisateur n'existe pas encore
  - `projectRole` optionnel, defaut `admin`
  - `roles` optionnel, defaut `["ROLE_USER"]`
- Si le user existe deja, la route cree ou met a jour son `ProjectMember`.
- Si le user n'existe pas, la route cree le user puis son `ProjectMember`.
- La route ne retourne jamais le hash password.
- A la creation d'un projet, l'utilisateur connecte est automatiquement rattache au projet
  en role `owner`, afin de pouvoir provisionner les premiers membres sans intervention SQL.

## Plan d'execution

- [x] Ajouter le controleur de provisioning membre projet.
- [x] Valider le payload et les roles autorises.
- [x] Creer ou reutiliser le user.
- [x] Creer ou mettre a jour le membership projet.
- [x] Rattacher automatiquement le createur d'un projet en `owner`.
- [x] Ajouter les tests fonctionnels.
- [x] Documenter l'appel API.

## Validations attendues

- [x] `docker compose exec api php bin/phpunit` passe.
- [x] `docker compose exec api php bin/console lint:container` passe.
