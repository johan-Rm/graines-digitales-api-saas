# Conventions REST

- Les routes projet utilisent `/api/projects/{projectId}/...`.
- Certaines routes projet peuvent exposer un identifiant metier quand l'interface ne
  connait pas encore l'UUID technique de la ressource. Exemple :
  `PUT /api/projects/{projectId}/accommodations/{identifier}?locale=fr`.
- `GET` lit une collection ou un item.
- `POST` cree une ressource.
- `PUT` remplace ou met a jour une ressource.
- `DELETE` supprime ou archive selon le comportement de la ressource.

Cas specifique :

- L'identite metier d'un `CategoryCode` est immutable apres creation : pas de `PUT` /
  `PATCH` racine pour modifier `codeValue`. Les labels localises restent modifiables
  via la route dediee `/translations`.
- `DELETE /api/projects/{projectId}/category-codes/{uuid}` archive un code libre et
  retourne `409` si le code est reference par un bien du meme projet.

Routes auth :

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/local-users` local-only, en `dev` et `test`
