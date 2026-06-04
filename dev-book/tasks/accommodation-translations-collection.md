# Accommodation translations collection

## Contrat

```txt
GET /api/projects/{projectId}/accommodations/{id}/translations
PUT /api/projects/{projectId}/accommodations/{id}/translations
```

- `GET /{id}/translations` retourne toutes les locales disponibles pour un bien.
- `PUT /{id}/translations` applique un bulk de traductions sur un bien existant.
- Les routes `GET /accommodations` et `GET /accommodations/{id}` restent localisees
  par `?locale=...` et n'exposent pas la collection `translations`.

## Vigilance

L'isolation inter-projets reste l'invariant principal : le `projectId` vient de
l'URL et l'acces passe par `ProjectAccessGuard`.
