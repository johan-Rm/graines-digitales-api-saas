# CLAUDE — Contexte projet : symfony-8-api-platform

Fichier de gouvernance local. Complète le socle global `~/.agents/.claude/CLAUDE.md` sans le
répéter. Les profils, principes et skills sont définis dans le global.

## Projet

API REST JSON-LD pour un CMS SaaS multi-tenant. Chaque tenant est un `Project` ; toutes les
ressources lui appartiennent via `ProjectMember`. L'accès à une ressource exige d'être membre
du projet propriétaire.

## Stack

- PHP 8.4, Symfony 8, API Platform 4.3
- Base de données : PostgreSQL, Doctrine ORM 3 / DBAL 4
- Authentification : LexikJWT + Gesdinet refresh token
- Upload : VichUploader
- Async : Symfony Messenger (`AnalyzeMediaObject`)
- CORS : NelmioCorsBunde
- Tests : PHPUnit (suite fonctionnelle API Platform + suite unitaire)
- Runtime conteneurisé : Docker Compose (`docker-compose.dev.yml`) — commandes via `make`

## Architecture — lecture obligatoire avant d'agir

`docs/` est la source de vérité. Lire selon le périmètre :

- `docs/02-system-design/` — frontières de modules, cycle de requête, règles de dépendances
- `docs/03-api-contracts/` — conventions REST, pagination, versioning, payloads
- `docs/04-domain-model/` — modèle métier, règles, cas d'usage
- `docs/05-data-layer/` — schéma BDD, migrations, repositories
- `docs/06-security/` — auth JWT, autorisation projet, validation, rate limiting
- `docs/07-integrations/` — jobs async, services externes
- `docs/09-quality/` — stratégie de tests, checklist de revue
- `docs/04-domain-model/translations.md` — modèle de traduction Sylius-style, locale resolution, auto-translation, auto-fill
- `docs/03-api-contracts/translations.md` — routes de traduction (PUT/POST/GET)
- `docs/04-domain-model/accommodations.md` — règles métier accommodation (identifiant, slug)

## Frontières de modules

```
src/Entity/          état persisté, invariants locaux, traits
src/State/           providers et processors API Platform (point central de la logique projet)
src/Security/        utilisateur courant (CurrentUserProvider), règles d'accès projet (ProjectAccessGuard)
src/Message/         messages Messenger
src/MessageHandler/  handlers Messenger (idempotents)
src/Controller/      controllers custom uniquement quand API Platform ne suffit pas
src/Serializer/      ajustements de normalisation (AccommodationNormalizer, MediaObjectNormalizer)
src/Encoder/         décodage multipart / fichiers uploadés
src/Translation/     auto-traduction et résolution de locale
src/Enum/            enums PHP natifs (AccommodationQuality, AiReviewStatus, ContentStatus, MediaStatus, ProjectRole)
```

## Pattern State central

Toute ressource project-scoped passe par ce pattern :

```
ProjectScopedCollectionProvider   → liste filtrée par projectId + guard
ProjectScopedItemProvider         → item vérifié par guard
ProjectScopedPersistProcessor     → écriture avec vérification membership
ProjectScopedRemoveProcessor      → suppression avec vérification membership
```

Les ressources traduisibles ont leurs variantes :

```
ProjectScopedTranslationCollectionProvider
ProjectScopedTranslationItemProvider
ProjectScopedTranslationPersistProcessor
```

Ne jamais contourner ces providers/processors pour accéder directement à Doctrine.

## Modèle de sécurité

Cycle de requête strict :

1. Firewall → valide le JWT
2. API Platform → résout l'opération
3. Provider/Processor → extrait `{projectId}` de l'URL
4. `ProjectAccessGuard` → vérifie le membership de l'utilisateur courant
5. Doctrine → charge ou persiste
6. Serializer → expose via groupes de sérialisation

**Ne jamais faire confiance à un `project` transmis dans le payload.** L'ID projet vient
toujours de l'URL.

Rôles membership : `owner`, `admin`, `editor` → lecture + écriture / `viewer` → lecture seule.

## Traits d'entité

```
UuidIdentity                → identifiant UUID v4
AuditTrail                  → createdAt / updatedAt
ProjectOwned                → lien project_id + ownership
AutoSlug                    → génération automatique de slug
TranslatableResourceBehavior → collection de translations
ResourceTranslationFields   → locale, root resource, audit
```

## Modèle de traduction (Sylius-style)

- Ressource racine : identité, appartenance projet, statut, audit, relations non localisées
- Ressource `*Translation` : champs éditoriaux localisés, `locale`, audit, lien vers la racine
- Traductions MVP : `WebPage`, `Article`, `CategoryCode`, `Accommodation`
- Contrainte unique : `(resource_id, locale)` par table de traduction
- Slugs localisés uniques par `(project_id, slug, locale)` pour `WebPage` et `Article`
- Locale source et locales actives définies par projet, stockées en base (`project.sourceLocale`, `project.enabledLocales`)
- Fallback : si traduction manquante, retour locale source avec `fallback: true`

Routes backoffice (pas de fallback côté backoffice) :

```
GET  /api/projects/{projectId}/web-pages/{id}/translations
GET  /api/projects/{projectId}/web-pages/{id}/translations/{locale}
PUT  /api/projects/{projectId}/web-pages/{id}/translations/{locale}
```

## Commandes courantes

Toutes les commandes passent par `make` (qui cible `docker-compose.dev.yml` en local).
Lancer `make help` pour la liste complète. L'organisation suit deux axes :

- cibles **liées à un environnement**, préfixées : `dev-*` (cycle de vie + opérations
  locales : `dev-migrate`, `dev-jwt`, `dev-console CMD="…"`, `dev-install`…),
  `preprod-*` / `prod-*` pour le déploiement
- cibles **globales** env-agnostiques, sans préfixe (`Makefile.common`) : tests
  (`test`, `test-filter`…), qualité (`quality`, `analyse`, `cs-*`, `rector-*`…),
  validation (`schema-validate`, `lint-container`), `diff-check`

Pendant une intervention IA courante, Claude ne doit pas lancer automatiquement toute la
suite PHPUnit. Utiliser des validations ciblees liees au changement, puis indiquer ce qui
n'a pas ete lance. La suite PHPUnit complete est reservee aux validations pre-merge et aux
merges vers `develop`, sauf demande explicite.

## Règles de développement

- chaque resource project-scoped doit passer par les providers/processors dédiés
- ne pas ajouter de controller custom si une opération API Platform suffit
- les handlers Messenger doivent être idempotents
- les traits d'entité sont la source d'autorité pour les champs transverses
- les enums PHP natifs sont préférés aux constantes de classe
- pas de `Any` ni de `mixed` non justifié dans les types
- les tests fonctionnels recreent le schéma et chargent les fixtures — ne pas dépendre de données insérées manuellement

## Points de vigilance spécifiques

- l'isolation inter-projets est une invariante de sécurité — toute régression est critique
- un changement de contrat DTO doit être répercuté dans `schemas/` (DGDOC)
- les migrations doivent être réversibles et compatibles avec un déploiement sans downtime
- `project_id` est indexé sur toutes les tables projet — vérifier lors d'une nouvelle entité
- les slugs localisés ont une contrainte unique composite — ne pas oublier lors d'une migration
