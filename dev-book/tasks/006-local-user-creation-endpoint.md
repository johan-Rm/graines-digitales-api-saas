# TASK-006 - Endpoint local de creation utilisateur

## Contexte

Le projet expose deja `POST /api/auth/login` et `POST /api/auth/refresh`, mais ne dispose
pas encore d'un endpoint public de creation de compte.

En local, la creation utilisateur reposait jusque-la sur une etape manuelle de hash :

```bash
docker compose exec api php bin/console security:hash-password --no-interaction 'admin123' 'App\Entity\User'
```

Cette approche depanne, mais elle est lente et fragile pour initialiser un environnement
de developpement ou de test.

## Objectif

Ajouter un endpoint strictement local permettant de creer un utilisateur applicatif sans
exposer une inscription publique en production.

## Scope

- Creer `POST /api/auth/local-users`.
- Autoriser la route sans JWT en environnement `dev` et `test`.
- Refuser la route hors environnement local/test.
- Hasher le mot de passe cote serveur avec le hasher Symfony configure pour `App\Entity\User`.
- Normaliser l'email en minuscule via l'entite `User`.
- Retourner `201 Created` avec les informations publiques minimales de l'utilisateur.
- Retourner `409 Conflict` si l'email existe deja.
- Documenter le endpoint dans la doc auth.

## Hors scope

- Inscription publique utilisateur.
- Verification email.
- Workflow invitation.
- Gestion avancee des roles ou RBAC.
- Creation automatique d'organisation, projet ou membership.

## Contrat API V1

```http
POST /api/auth/local-users
Content-Type: application/json
```

```json
{
  "email": "admin@example.test",
  "password": "admin123",
  "roles": ["ROLE_ADMIN"]
}
```

`roles` est optionnel. Pour l'usage local de bootstrap, la valeur par defaut est
`["ROLE_ADMIN"]`.

## Criteres d'acceptation

- [x] La route cree un utilisateur en `dev` et `test`.
- [x] Le mot de passe stocke est hashe, jamais persiste en clair.
- [x] Un email deja existant retourne `409`.
- [x] Un payload invalide retourne `400`.
- [x] La route n'est pas utilisable hors environnement local/test.
- [x] La documentation d'authentification mentionne explicitement le caractere local-only.
