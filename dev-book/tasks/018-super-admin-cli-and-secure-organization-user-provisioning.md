# Task : Commande super admin et provisioning utilisateur par organisation

## Metadonnees

- ID : TASK-018
- Source : cadrage securisation creation utilisateur du 2026-05-25
- Statut : A faire
- Perimetre : securite, commandes Symfony, organisations, utilisateurs, memberships
- Dependances fonctionnelles : TASK-006, TASK-016

## Objectif

Remplacer le bootstrap utilisateur trop ouvert par un flux explicite et auditable :

- creer ou mettre a jour l'utilisateur super admin via une commande Symfony
- conserver la source de verite du droit super admin dans `PLATFORM_SUPER_ADMIN_EMAILS`
- creer les utilisateurs clients uniquement dans le contexte d'une organisation
- rattacher automatiquement tout utilisateur cree par le flux metier a un
  `OrganizationMember`

## Constat actuel

- `POST /api/auth/local-users` cree des utilisateurs en `dev` et `test`.
- Cette route est publique dans `security.yaml` pour faciliter le bootstrap local.
- Le role `ROLE_SUPER_ADMIN` est bien refuse par payload API, mais l'endpoint reste un
  raccourci technique qui ne represente pas le flux SaaS cible.
- Depuis TASK-016, un utilisateur doit deja appartenir a l'organisation avant de pouvoir
  devenir `ProjectMember`.
- Il manque encore une route metier pour lister et ajouter les membres d'une
  organisation.

## Decision produit

Le flux cible devient :

1. Le super admin plateforme est cree ou mis a jour par commande Symfony.
2. Son email est declare dans `PLATFORM_SUPER_ADMIN_EMAILS`.
3. Le super admin cree l'organisation.
4. Le super admin ajoute le premier `OrganizationMember(owner)`.
5. Les `OrganizationMember(owner|admin)` ajoutent ensuite les autres membres de leur
   organisation.
6. Seuls les utilisateurs deja membres de l'organisation peuvent etre ajoutes a ses
   projets.

## Contrat MVP

### Commande super admin

Ajouter une commande Symfony :

```bash
php bin/console app:user:create-super-admin admin@example.com
```

Comportement attendu :

- accepter un email obligatoire
- accepter un mot de passe via option interactive ou option CLI non loggee si possible
- creer l'utilisateur s'il n'existe pas
- mettre a jour son mot de passe si l'utilisateur existe et si un nouveau mot de passe est
  fourni
- attribuer au minimum `ROLE_ADMIN` en base pour conserver les droits Symfony classiques
- ne pas stocker `ROLE_SUPER_ADMIN` comme source de verite en base
- afficher un avertissement si l'email n'est pas present dans
  `PLATFORM_SUPER_ADMIN_EMAILS`
- etre idempotente

Exemples de commandes envisagees :

```bash
php bin/console app:user:create-super-admin admin@example.com
php bin/console app:user:create-super-admin admin@example.com --password
```

### Route de membres organisation

Ajouter les routes :

```http
GET  /api/organizations/{organizationId}/members
POST /api/organizations/{organizationId}/members
```

Payload de creation minimal :

```json
{
  "email": "user@example.com",
  "password": "password123",
  "role": "member"
}
```

Payload pour utilisateur existant :

```json
{
  "email": "user@example.com",
  "role": "admin"
}
```

Roles acceptes :

- `owner`
- `admin`
- `member`
- `billing`

Regles :

- `ROLE_SUPER_ADMIN` peut lister et ajouter des membres dans toute organisation.
- `OrganizationMember(owner|admin)` peut lister et ajouter des membres dans son
  organisation.
- `OrganizationMember(member|billing)` ne peut pas gerer les membres.
- un utilisateur cree par cette route est automatiquement rattache a l'organisation.
- si l'utilisateur existe deja, la route cree ou met a jour son `OrganizationMember`.
- la paire `(organization_id, user_id)` reste unique.
- la route ne doit jamais accepter `ROLE_SUPER_ADMIN` ni de roles Symfony globaux depuis
  le payload public.
- la route ne doit pas exposer les hashes de mot de passe.

### Route locale existante

Revoir `POST /api/auth/local-users` :

- conserver uniquement pour `dev` et `test`
- documenter explicitement qu'elle est reservee au bootstrap local
- envisager une protection additionnelle par secret local ou suppression a terme
- ne pas l'utiliser comme flux metier SaaS

## Plan d'execution

- [ ] Ajouter une commande Symfony `app:user:create-super-admin`.
- [ ] Ajouter les tests de commande pour creation, idempotence et avertissement `.env`.
- [ ] Ajouter un endpoint `GET /api/organizations/{organizationId}/members`.
- [ ] Ajouter un endpoint `POST /api/organizations/{organizationId}/members`.
- [ ] Ajouter un guard dedie ou completer `OrganizationAccessGuard` pour la gestion des
      membres organisation.
- [ ] Valider les payloads avec contraintes Symfony.
- [ ] Refuser toute elevation de privilege via roles Symfony dans les payloads publics.
- [ ] Adapter la documentation d'authentification et d'autorisation.
- [ ] Ajouter les tests fonctionnels d'isolation et de refus d'acces.

## Tests attendus

- La commande cree un utilisateur super admin avec `ROLE_ADMIN`.
- La commande est idempotente si l'utilisateur existe deja.
- La commande avertit si l'email n'est pas declare dans `PLATFORM_SUPER_ADMIN_EMAILS`.
- Un super admin peut ajouter un membre a une organisation.
- Un `OrganizationMember(owner|admin)` peut ajouter un membre a sa propre organisation.
- Un `OrganizationMember(member|billing)` ne peut pas ajouter de membre.
- Un membre d'une autre organisation ne peut pas lister ni ajouter les membres.
- Un payload ne peut pas attribuer `ROLE_SUPER_ADMIN`.
- Un utilisateur cree par la route organisation devient `OrganizationMember`.
- Un utilisateur deja existant peut etre rattache a une organisation sans duplicat.

## Validation

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

## Points de vigilance

- Securite : ne jamais faire de la route user un endpoint public hors contexte
  organisation.
- Audit : tracer l'acteur sur les creations et mises a jour de `OrganizationMember`.
- Isolation : masquer ou refuser les organisations hors scope.
- DevEx : garder un chemin de bootstrap local simple sans ouvrir un bypass production.
