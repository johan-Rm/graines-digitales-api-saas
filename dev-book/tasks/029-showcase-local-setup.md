# TASK-029 — Setup Showcase et stockage media

**Statut** : ✅ fait le 2026-10-05
**Type** : feat
**Tests** : BootstrapManualCommandTest, fixtures et tests existants adaptes ; validation Nginx et HTTP.

## Cadrage

Renommer le projet de demonstration, cibler son bootstrap et son import via Make, regrouper le stockage VPS et declarer les vhosts Docker. Export Insomnia partage sans mots de passe.

Carte redigee apres implementation, lors de la preparation de la fusion.

## Documentation impactee

docs/10-operations/bootstrap-manuel.md, deployment.md, environments.md et README.md

## Verification

Suite complete : 144 tests / 1228 assertions. Nouveau test pagination et login :
2 tests / 15 assertions. Lint container, PHPStan, style, Deptrac et validation
Doctrine passes. Vhost Nginx teste HTTP 200 depuis WSL et Windows pour
api.localhost ; alias api.graines-digitales.local teste avec resolution forcee.

## Definition de termine

- [x] Controles qualite et tests passes.
- [x] Documentation adaptee.
- [x] Aucun fichier .env, cle privee ou mot de passe Insomnia inclus.
