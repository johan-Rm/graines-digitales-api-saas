# Task : Super admin plateforme et memberships d'organisation

## Metadonnees

- ID : TASK-016
- Source : cadrage role plateforme et organisation du 2026-05-25
- Statut : A faire
- Perimetre : securite, organisations, memberships, API Platform
- Dependances fonctionnelles : TASK-001, TASK-006, TASK-010

## Objectif

Clarifier et implementer un niveau d'administration plateforme pour permettre au
developpeur ou operateur SaaS de creer les organisations sans affaiblir l'isolation
inter-projets.

La tache doit aussi introduire un membership d'organisation distinct du membership projet,
afin de representer proprement les droits globaux d'un client sur son organisation.

## Constat actuel

- `ROLE_ADMIN` est utilise comme role global Symfony pour acceder a des ressources
  structurantes comme `Organization`, `Project` et `ProjectMember`.
- Les acces aux contenus restent controles par `ProjectMember` et `ProjectAccessGuard`.
- Les membres sont aujourd'hui rattaches a un projet, mais aucun modele ne represente
  l'appartenance d'un utilisateur a une organisation.
- La route `POST /api/projects/{projectId}/members` verifie seulement un acces projet en
  ecriture. Cela permet a un `editor` de provisionner ou modifier des memberships projet,
  ce qui doit etre durci.

## Decision produit

Ajouter deux niveaux explicites :

- `ROLE_SUPER_ADMIN` : role Symfony global reserve a l'operateur plateforme.
- `OrganizationMember` : membership metier entre un `User` et une `Organization`.

Conserver `ProjectMember` comme source d'autorisation pour les contenus projet.

## Contrat MVP

### Role plateforme

- Definir le ou les super admins depuis la configuration d'environnement, pas depuis un
  payload public.
- Ajouter une variable d'environnement dediee, par exemple
  `PLATFORM_SUPER_ADMIN_EMAILS`, contenant une liste d'emails autorises.
- Ne jamais accepter `ROLE_SUPER_ADMIN` depuis `POST /api/auth/local-users` ou
  `POST /api/projects/{projectId}/members`.
- Configurer une hierarchie Symfony :

```yaml
role_hierarchy:
    ROLE_ADMIN: ROLE_USER
    ROLE_SUPER_ADMIN: ROLE_ADMIN
```

- Restreindre la creation des `Organization` au `ROLE_SUPER_ADMIN`.
- Ne pas permettre a un utilisateur client ou a un `OrganizationMember` de creer une
  nouvelle organisation.
- Les operations internes a une organisation existante peuvent ensuite etre portees par
  `OrganizationMember`, selon le niveau de droit attendu.
- Eviter tout bypass implicite de `ProjectAccessGuard` sur les routes
  `/api/projects/{projectId}/...`.
- Les interventions super admin sur les donnees tenant doivent passer par des endpoints
  explicites ou par la creation auditee d'un membership.
- Le role super admin effectif doit etre resolu cote serveur a partir de l'utilisateur
  authentifie et de la configuration, afin d'eviter une elevation de privilege par API.

### Membership organisation

Creer une entite `OrganizationMember` avec :

- `organization`
- `user`
- `role`
- unicite `(organization_id, user_id)`
- index sur `organization_id`
- index sur `user_id`
- audit trail existant si compatible avec les patterns du projet

Roles organisation proposes :

- `owner`
- `admin`
- `member`
- `billing`

Regles MVP :

- `owner` et `admin` peuvent gerer les membres de l'organisation.
- `owner` et `admin` peuvent creer des projets dans l'organisation.
- `billing` est reserve a la facturation et ne donne pas d'acces contenu par defaut.
- `member` represente une appartenance simple sans droit global d'administration.
- L'acces aux contenus projet reste controle par `ProjectMember`.

### Creation projet

- Lorsqu'un utilisateur autorise cree un projet dans une organisation, il devient
  automatiquement `ProjectMember(owner)` de ce projet.
- Si l'utilisateur n'est pas `ROLE_SUPER_ADMIN`, il doit etre
  `OrganizationMember(owner|admin)` de l'organisation cible.
- Le `project_id` ne doit jamais venir du body pour les ressources project-scoped.

### Gestion des membres projet

- Durcir `POST /api/projects/{projectId}/members`.
- Autoriser la gestion des membres projet uniquement a :
  - `ProjectMember(owner|admin)` du projet cible
  - ou `OrganizationMember(owner|admin)` de l'organisation du projet, si cette delegation
    est retenue
  - ou `ROLE_SUPER_ADMIN` via un chemin audite et explicite
- Interdire a un `editor` de creer, promouvoir ou retrograder des membres projet.
- Imposer que l'utilisateur cible soit deja `OrganizationMember` de l'organisation
  proprietaire avant toute creation ou mise a jour de `ProjectMember`.

## Plan d'execution

- [ ] Rejeter `ROLE_SUPER_ADMIN` dans les payloads de creation utilisateur locales et de
      provisioning projet.
- [ ] Ajouter la variable `PLATFORM_SUPER_ADMIN_EMAILS` dans `.env.example`.
- [ ] Ajouter un service de resolution du super admin depuis l'environnement.
- [ ] Ajouter `role_hierarchy` dans `config/packages/security.yaml`.
- [ ] Restreindre la creation d'`Organization` a `ROLE_SUPER_ADMIN`.
- [ ] Creer l'enum `OrganizationRole`.
- [ ] Creer l'entite `OrganizationMember`.
- [ ] Ajouter la migration Doctrine reversible.
- [ ] Ajouter un guard ou service dedie pour les droits organisation.
- [ ] Adapter la creation de projet pour verifier les droits organisation.
- [ ] Durcir la gestion des membres projet contre l'elevation de privilege par `editor`.
- [ ] Ajouter les tests fonctionnels de securite.
- [ ] Mettre a jour la documentation securite et modele domaine.

## Tests attendus

- Un `ROLE_SUPER_ADMIN` peut creer une organisation.
- Un utilisateur dont l'email est declare dans `PLATFORM_SUPER_ADMIN_EMAILS` obtient les
  droits plateforme attendus.
- Un payload API ne peut pas promouvoir un utilisateur non autorise en `ROLE_SUPER_ADMIN`.
- Un `ROLE_ADMIN` non super admin ne peut plus creer une organisation.
- Un `OrganizationMember(owner|admin)` ne peut pas creer une nouvelle organisation.
- Un `OrganizationMember(owner|admin)` peut creer un projet dans son organisation.
- Un utilisateur sans membership organisation ne peut pas creer de projet dans cette
  organisation.
- Le createur autorise d'un projet devient `ProjectMember(owner)`.
- Un utilisateur qui n'est pas `OrganizationMember` de l'organisation du projet ne peut
  pas etre ajoute comme `ProjectMember`.
- Un `ProjectMember(editor)` ne peut pas ajouter ou modifier des membres projet.
- Un `ProjectMember(viewer)` conserve un acces lecture uniquement.
- Un utilisateur sans `ProjectMember` ne peut pas lire les contenus d'un projet, meme s'il
  appartient a une autre organisation.
- L'isolation inter-projets reste masquee ou refusee cote serveur.

## Validation

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

## Points de vigilance

- Securite : ne pas transformer `ROLE_SUPER_ADMIN` en bypass general des contenus tenant.
- Isolation : `ProjectAccessGuard` reste le point central des routes project-scoped.
- Audit : les actions super admin et les changements de memberships doivent etre
  tracables.
- Migration : ajouter les index requis et conserver une migration reversible.
- Contrat API : tout nouveau DTO ou payload expose doit etre documente dans `docs/` et,
  si applicable, dans les schemas DGDOC.
