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

## Super admin plateforme

Le role plateforme `ROLE_SUPER_ADMIN` n'est pas accorde par payload API.

Il est resolu cote serveur a partir de `PLATFORM_SUPER_ADMIN_EMAILS`, une liste d'emails
separes par des virgules. Un utilisateur dont l'email est declare dans cette variable
obtient les droits plateforme a l'execution.

Cette configuration sert notamment a reserver la creation des organisations a l'operateur
SaaS.

Le super admin peut aussi lire et modifier les ressources project-scoped en local. Les
utilisateurs projet standard doivent rester membres du projet cible via `ProjectMember`.
