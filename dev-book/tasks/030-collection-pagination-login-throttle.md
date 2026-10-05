# TASK-030 — Pagination et limitation des connexions

**Statut** : ✅ fait le 2026-10-05
**Type** : feat
**Tests** : CollectionPaginationAndLoginThrottleTest, ProjectIsolationTest et tests media/seed existants.

## Cadrage

Conserver la pagination des collections dans le tenant autorise, limiter les echecs de connexion, corriger les references media et retirer deux exceptions Deptrac obsoletes.

Carte redigee apres implementation, lors de la preparation de la fusion.

## Documentation impactee

docs/03-api-contracts/pagination-filtering-sorting.md et docs/06-security/rate-limiting.md

## Verification

Suite complete : 144 tests / 1228 assertions. Nouveau test pagination et login :
2 tests / 15 assertions. Lint container, PHPStan, style, Deptrac et validation
Doctrine passes. Vhost Nginx teste HTTP 200 depuis WSL et Windows pour
api.localhost ; alias api.graines-digitales.local teste avec resolution forcee.

## Definition de termine

- [x] Controles qualite et tests passes.
- [x] Documentation adaptee.
- [x] Aucun fichier .env, cle privee ou mot de passe Insomnia inclus.
