# Conventions REST

- Les routes projet utilisent `/api/projects/{projectId}/...`.
- Les routes dashboard peuvent exposer un identifiant metier quand l'interface ne connait
  pas encore l'UUID technique de la ressource. Exemple :
  `PUT /api/dashboard/accommodations/{identifier}?locale=fr`.
- `GET` lit une collection ou un item.
- `POST` cree une ressource.
- `PUT` remplace ou met a jour une ressource.
- `DELETE` supprime ou archive selon le comportement de la ressource.

Routes auth :

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/local-users` local-only, en `dev` et `test`
