# Conventions REST

- Les routes projet utilisent `/api/projects/{projectId}/...`.
- `GET` lit une collection ou un item.
- `POST` cree une ressource.
- `PUT` remplace ou met a jour une ressource.
- `DELETE` supprime ou archive selon le comportement de la ressource.

Routes auth :

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/local-users` local-only, en `dev` et `test`
