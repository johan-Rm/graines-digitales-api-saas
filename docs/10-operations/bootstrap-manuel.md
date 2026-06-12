# Bootstrap backend depuis zero

Procedure manuelle pour initialiser un backend fonctionnel a partir d'un
environnement Docker completement vierge.

## Prerequis

- Docker Compose operationnel
- `.env.local` configure : DSN PostgreSQL, cles JWT, `PLATFORM_SUPER_ADMIN_EMAILS`
- `.env` configure avec `SEED_PROJECT_HOST_DIR` vers le dossier seed local

---

## 0 — Reset de l'environnement

Si un environnement precedent existe, repartir proprement :

```bash
docker compose down --volumes --remove-orphans
docker compose up -d
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

Les etapes 1 a 17 peuvent ensuite etre executees automatiquement :

```bash
docker compose exec api php bin/console app:bootstrap:manual --password=password1234
```

La commande est idempotente : elle cree ou met a jour les utilisateurs,
organisations, projets et memberships attendus, puis affiche les UUID projets a
reporter cote frontend.

Le mot de passe doit etre fourni explicitement :

```bash
docker compose exec api php bin/console app:bootstrap:manual --password=password1234 --site-url=http://localhost:3000
```

---

## 1 — Super admin

```bash
docker compose exec api php bin/console app:user:create-super-admin developer@graines-digitales.online --password=password1234
```

L'email doit figurer dans `PLATFORM_SUPER_ADMIN_EMAILS` pour que le compte
soit reconnu comme super admin a l'execution.

---

## 2 — Organisation et premier membre

```bash
docker compose exec api php bin/console app:organization:create "MLK - My Little Kasbah" --owner-email=johan.remy@graines-digitales.online --owner-password=password1234
```

---

## 3 — Token JWT

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "johan.remy@graines-digitales.online",
  "password": "password1234"
}
```

Conserver le `token` retourne pour les requetes suivantes.

---

## 4 — UUID de l'organisation

```http
GET /api/organizations
Authorization: Bearer {token}
```

---

## 5 — Deuxieme membre de l'organisation

```http
POST /api/organizations/{uuid}/members
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "email": "buzac@mlk-my-little-kasbah.immo",
  "password": "password1234",
  "role": "admin"
}
```

---

## 6 — Champs metier de l'organisation

```http
PUT /api/organizations/{uuid}
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "name": "MLK - My Little Kasbah",
  "acronym": "MLK",
  "fullName": "MLK - My Little Kasbah",
  "location": "Essaouira",
  "email": ["contact@mlk-my-little-kasbah.immo"],
  "phoneNumbers": ["+33 (0)7 67 235 008", "+212 (0)7 26 403 203"],
  "description": "MLK – My Little Kasbah accompagne vos projets immobiliers à Essaouira : investissement, achat, vente et gestion locative avec une expertise locale et internationale."
}
```

---

## 7 — Projet

```http
POST /api/projects
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "organization": "/api/organizations/{uuid}",
  "name": "MLK - Modern Site Web",
  "sourceLocale": "fr",
  "enabledLocales": ["fr", "en", "es"],
  "mediaPath": "mlk-my-little-kasbah/public/images",
  "mediaBaseUrl": "{SITE_URL}/images"
}
```

Noter l'`@id` retourne (`/api/projects/{projectUuid}`).

---

## 8 — Membres du projet

L'utilisateur doit etre membre de l'organisation liee au projet avant d'etre
ajoute au projet. Champs valides : `email` (requis), `role` (optionnel,
defaut `admin` ; valeurs : `owner`, `admin`, `editor`, `viewer`).

```http
POST /api/projects/{projectUuid}/members
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "email": "johan.remy@graines-digitales.online",
  "role": "owner"
}
```

```http
POST /api/projects/{projectUuid}/members
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "email": "buzac@mlk-my-little-kasbah.immo",
  "role": "admin"
}
```

---

## 9 — Configuration frontend

```env
SYMFONY_PROJECT_ID={projectUuid}
```

---

## 10 — Deuxieme organisation

```bash
docker compose exec api php bin/console app:organization:create "Blue Bay Mogador" --owner-email=johan.remy+1@graines-digitales.online --owner-password=password1234
```

---

## 11 — Token JWT

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "johan.remy+1@graines-digitales.online",
  "password": "password1234"
}
```

Conserver le `token` retourne pour les requetes suivantes.

## 12 — UUID de la deuxieme organisation

```http
GET /api/organizations
Authorization: Bearer {token}
```

Noter l'UUID de l'organisation `Blue Bay Mogador`
(`{blueBayOrganizationUuid}`).

---

## 13 — Deuxieme membre de l'organisation

```http
POST /api/organizations/{uuid}/members
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "email": "manager@blue-bay-mogador.test",
  "password": "password1234",
  "role": "admin"
}
```

---

## 14 — Champs metier de la deuxieme organisation

```http
PUT /api/organizations/{blueBayOrganizationUuid}
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "name": "Blue Bay Mogador",
  "acronym": "BBM",
  "fullName": "Blue Bay Mogador",
  "location": "Essaouira",
  "email": ["contact@blue-bay-mogador.test"],
  "phoneNumbers": ["+212 (0)6 12 345 678"],
  "description": "Blue Bay Mogador est une organisation de demonstration dediee aux tests multi-projets et a la validation de l'isolation des donnees."
}
```

---

## 15 — Deuxieme projet

Ce projet n'est pas destine a representer un vrai site client. Il sert a
verifier les parcours multi-projets et l'isolation des donnees.

```http
POST /api/projects
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "organization": "/api/organizations/{blueBayOrganizationUuid}",
  "name": "blue-bay-mogador",
  "sourceLocale": "fr",
  "enabledLocales": ["fr", "en"],
  "mediaPath": "blue-bay-mogador/public/images",
  "mediaBaseUrl": "{SITE_URL}/blue-bay-mogador/images"
}
```

Noter l'`@id` retourne (`/api/projects/{blueBayProjectUuid}`).

---

## 16 — Membres du deuxieme projet

L'utilisateur doit etre membre de l'organisation Blue Bay avant d'etre ajoute au
projet.

```http
POST /api/projects/{blueBayProjectUuid}/members
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "email": "johan.remy+1@graines-digitales.online",
  "role": "owner"
}
```

```http
POST /api/organizations/{blueBayOrganizationUuid}/members
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "email": "manager@blue-bay-mogador.test",
  "role": "admin"
}
```

---

## 17 — Configuration frontend secondaire

Si le frontend doit cibler le projet secondaire :

```env
SYMFONY_PROJECT_ID={blueBayProjectUuid}
```

---

## Chargement de donnees projet

La commande historique `app:fixtures:load-demo` a ete supprimee.
Le chargement de donnees projet doit passer par la commande `app:seed:load-project`, decrite dans
`dev-book/tasks/022-real-data-project-resource-loader.md`.

La commande commence toujours par un preflight sans ecriture. Elle analyse le dossier
source, affiche les volumes detectes et bloque l'import si une reference critique est
absente.

```bash
docker compose exec api php bin/console app:seed:load-project blue-bay-mogador
```

En mode interactif, la commande demande les familles d'entites a importer puis une
confirmation avant toute ecriture.

En mode non interactif, l'import doit etre explicitement confirme :

```bash
docker compose exec -T api php bin/console app:seed:load-project blue-bay-mogador --no-interaction --force
```

Il est possible de limiter les familles importees :

```bash
docker compose exec -T api php bin/console app:seed:load-project blue-bay-mogador --no-interaction --force --only=metadata,media
```

Familles disponibles : `metadata`, `media`, `web-pages`, `accommodations`.
