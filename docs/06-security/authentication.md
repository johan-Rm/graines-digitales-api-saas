# Authentification

- JWT : `lexik/jwt-authentication-bundle`.
- Login : `POST /api/auth/login`.
- Refresh : `POST /api/auth/refresh`.
- Refresh token MVP : `gesdinet/jwt-refresh-token-bundle`.

Les routes `/api/projects/{projectId}/...` exigent un JWT valide.

## Creation utilisateur locale

`POST /api/auth/local-users` permet de creer un utilisateur uniquement en environnement
`dev` ou `test`.

Cette route sert au bootstrap local tant que le produit ne dispose pas d'un vrai parcours
d'inscription, d'invitation ou de provisionnement backoffice. Elle ne doit pas devenir
un endpoint public de creation de compte en production.

Payload minimal :

```json
{
  "email": "admin@example.test",
  "password": "admin123",
  "roles": ["ROLE_ADMIN"]
}
```

`roles` est optionnel et vaut `["ROLE_ADMIN"]` par defaut pour l'usage local.
