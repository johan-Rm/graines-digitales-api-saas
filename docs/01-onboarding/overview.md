# Onboarding local

Cette page explique comment initialiser le projet, creer un utilisateur local,
obtenir un token JWT et faire un premier appel API authentifie.

## Demarrer

```bash
cp .env.example .env.local
docker compose up -d --build
docker compose exec api composer install
docker compose exec api php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

API locale par defaut :

```txt
http://localhost:8080
```

Si `API_HTTP_PORT` est defini localement, utiliser ce port.

## Creer un utilisateur local

Le projet n'a pas encore d'endpoint public de creation de compte. En local,
generer d'abord un hash :

```bash
docker compose exec api php bin/console security:hash-password --no-interaction 'admin123' 'App\Entity\User'
```

Copier le hash affiche, puis ouvrir `psql` :

```bash
docker compose exec postgres psql -U cms -d cms
```

Executer ensuite, en remplacant `COLLER_LE_HASH_ICI` :

```sql
WITH organization_row AS (
    INSERT INTO organization (id, name, slug, created_at, updated_at)
    VALUES (gen_random_uuid(), 'Demo Organization', 'demo-organization', now(), now())
    ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name
    RETURNING id
),
project_existing AS (
    SELECT id FROM project WHERE slug = 'demo-project' LIMIT 1
),
project_inserted AS (
    INSERT INTO project (id, organization_id, name, slug, created_at, updated_at)
    SELECT gen_random_uuid(), organization_row.id, 'Demo Project', 'demo-project', now(), now()
    FROM organization_row
    WHERE NOT EXISTS (SELECT 1 FROM project_existing)
    RETURNING id
),
project_row AS (
    SELECT id FROM project_existing
    UNION ALL
    SELECT id FROM project_inserted
),
user_row AS (
    INSERT INTO "user" (id, email, password, roles, created_at, updated_at)
    VALUES (
        gen_random_uuid(),
        'admin@example.test',
        'COLLER_LE_HASH_ICI',
        '["ROLE_ADMIN"]',
        now(),
        now()
    )
    ON CONFLICT (email) DO UPDATE
    SET password = EXCLUDED.password,
        roles = EXCLUDED.roles,
        updated_at = now()
    RETURNING id
)
INSERT INTO project_member (id, project_id, user_id, role, created_at, updated_at)
SELECT gen_random_uuid(), project_row.id, user_row.id, 'owner', now(), now()
FROM project_row, user_row
WHERE NOT EXISTS (
    SELECT 1
    FROM project_member
    WHERE project_id = project_row.id
      AND user_id = user_row.id
);
```

Recuperer le `projectId` :

```sql
SELECT id, name, slug FROM project WHERE slug = 'demo-project';
```

## Obtenir un token JWT

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.test","password":"admin123"}'
```

La reponse contient `token` et `refresh_token`.

## Faire un premier appel API

Remplacer `PROJECT_ID` et `TOKEN`.

```bash
curl http://localhost:8080/api/projects/PROJECT_ID/category-codes \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/ld+json"
```

## Creer une ressource de test

```bash
curl -X POST http://localhost:8080/api/projects/PROJECT_ID/category-codes \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/ld+json" \
  -d '{
    "code": "property-type",
    "label": "Type de bien"
  }'
```

## Suite attendue

TASK-006 doit remplacer ces manipulations SQL par une commande Symfony
idempotente de seed dev.
