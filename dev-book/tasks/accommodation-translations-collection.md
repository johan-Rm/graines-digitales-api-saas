# Accommodation translations collection

## Objectif

Exposer une lecture backoffice permettant de recuperer toutes les traductions des
`Accommodation` d'un projet en un seul appel, sans alourdir la route localisee
standard.

## Contrat

```txt
GET /api/projects/{projectId}/accommodation-translations
GET /api/projects/{projectId}/accommodations/{id}/translations
```

- La route collection retourne uniquement les traductions des biens du projet
  demande.
- Les biens archives restent exclus.
- Chaque traduction expose l'identifiant UUID du bien parent via `accommodation`.
- Les routes existantes `GET /accommodations` et `GET /accommodations/{id}` restent
  localisees par `?locale=...` et ne retournent pas la collection technique
  `translations`.

## Vigilance

L'isolation inter-projets reste l'invariant principal : le `projectId` vient de
l'URL et l'acces passe par `ProjectAccessGuard`.
