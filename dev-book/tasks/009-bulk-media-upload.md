# Task : Upload image bulk par projet

## Metadonnees

- ID : TASK-009
- Source : besoin utilisateur du 2026-05-21
- Statut : En validation partielle
- Perimetre : API Platform, upload multipart, MediaObject, documentation
- Dependances fonctionnelles : TASK-001, TASK-003, TASK-005

## Objectif

Permettre a un client d'uploader plusieurs images en une seule requete pour accelerer
l'alimentation des biens immobiliers qui portent beaucoup de visuels.

## Contrat MVP

- Route : `POST /api/projects/{projectId}/media-objects/bulk`
- Format : `multipart/form-data`
- Champ fichiers : `files[]`
- Limite : 20 fichiers par requete.
- Limite fichier : 12M par image, formats identiques a l'upload unitaire.
- Champs optionnels appliques a tous les fichiers :
  - `finalAlt`
  - `finalDescription`
- Reponse :
  - `201 Created` si tous les fichiers valides sont crees
  - `207 Multi-Status` si certains fichiers sont crees et d'autres rejetes
  - `400 Bad Request` si aucun fichier n'est fourni
  - `422 Unprocessable Entity` si aucun fichier n'est cree a cause de validations

## Plan d'execution

- [x] Ajouter l'operation API Platform bulk.
- [x] Implementer le controleur applicatif.
- [x] Conserver l'upload unitaire existant.
- [x] Ajouter les tests fonctionnels bulk.
- [x] Documenter la route et les limites.
- [x] Ajuster la limite `post_max_size` Docker pour un vrai bulk local.

## Validations attendues

- [x] `docker compose exec api php bin/phpunit` passe.
- [x] `docker compose exec api php bin/console lint:container` passe.
- [ ] `docker compose exec api php bin/console doctrine:schema:validate` passe.

## Notes de validation

- `doctrine:schema:validate` ne doit pas signaler de diff hors TASK-009.
