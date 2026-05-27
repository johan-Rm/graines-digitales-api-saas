# AGENTS — Contexte projet : symfony-8-api-platform

Fichier de gouvernance local pour Codex. Complète le socle global `~/.agents/AGENTS.md`
sans le répéter.

## Mission produit

API REST JSON-LD pour un CMS SaaS multi-tenant. Chaque `Project` représente un tenant.
Toutes les ressources lui appartiennent ; l'accès est conditionné à un `ProjectMember`
actif. La sécurité est une invariante de premier ordre — toute régression d'isolation
inter-projets est bloquante.

## Priorités

1. isolation inter-projets — jamais de fuite de données entre tenants
2. contrats API stables — tout changement de DTO se répercute dans `schemas/` (DGDOC)
3. tests fonctionnels verts avant merge
4. pas de controller custom si une opération API Platform suffit

## Stack

PHP 8.4 / Symfony 8 / API Platform 4.3 / PostgreSQL / Doctrine ORM / LexikJWT / VichUploader / Symfony Messenger

## Architecture

Toutes les ressources project-scoped passent obligatoirement par :

```
ProjectScopedCollectionProvider / ItemProvider / PersistProcessor / RemoveProcessor
ProjectScopedTranslationCollectionProvider / ItemProvider / PersistProcessor
```

Frontières :

- `src/Entity/` — état persisté, traits
- `src/State/` — logique project-scoped (point central)
- `src/Security/` — JWT, garde projet
- `src/Translation/` — auto-traduction, résolution locale
- `src/Message/` + `src/MessageHandler/` — async (Messenger, idempotent)
- `src/Controller/` — custom uniquement si API Platform ne suffit pas

## Modèle de traduction

Sylius-style : ressource racine (identité, ownership) + `*Translation` (champs localisés).
Ressources traduisibles MVP : `WebPage`, `Article`, `CategoryCode`, `Accommodation`.
Lire `docs/TRANSLATIONS.md` avant toute modification du modèle de traduction.

## Sécurité

- JWT extrait du firewall Symfony — jamais du payload
- `project_id` vient toujours de l'URL, jamais du body
- `ProjectAccessGuard` vérifie le membership avant toute opération

## Commandes

Pendant une intervention IA courante, ne pas lancer automatiquement toute la suite PHPUnit.
Privilégier les validations ciblées liées au fichier ou au comportement modifié, puis
documenter ce qui n'a pas été lancé.

La suite PHPUnit complète est réservée aux validations pré-merge et aux merges vers
`develop`, sauf demande explicite.

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

## Contraintes

- pas de secret en clair, pas de `.env` committé
- pas de régression sur les tests d'isolation inter-projets
- migrations réversibles et compatibles déploiement sans downtime
- `project_id` indexé sur toute nouvelle table projet
- handlers Messenger idempotents
