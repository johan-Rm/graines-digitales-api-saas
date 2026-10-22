# TASK-031 — Corriger PHP2Shell par mise a jour de l'image PHP

**Statut** : ✅ fait le 2026-10-22
**Type** : fix
**Tests** : constructions dev/prod, requetes FPM reelles, lint Symfony et suite PHPUnit existante sous PHP 8.4.27.

## Cadrage

Remplacer la base PHP 8.4 flottante des deux Dockerfiles par l'image officielle
`php:8.4.27-fpm-alpine`, corrigee pour CVE-2026-109618 et CVE-2026-109619.
Conserver les dependances Composer et le schema de base. L'API et le worker
Messenger devront etre recrees avec la meme image lors du deploiement.

Carte redigee apres implementation pour consigner la demande directe de la
conversation. L'utilisateur autorise explicitement commit, merge et push master.
Aucun commit direct sur master : integration de la branche de correction.

## Livré

- `Dockerfile` et `Dockerfile.prod` : base PHP 8.4.27 explicite.
- `docs/10-operations/deployment.md` : procedure et verifications PHP2Shell.

## Verification

- Deux images construites avec succes et controles FPM passes : version,
  extensions, sessions et upload multipart ; pcov present en developpement.
- `make lint-container` reussi sur l'image de production avec les parametres exemples.
- Tests unitaires : 27 tests, 86 assertions sous PHP 8.4.27.
- Suite pre-merge : environnement PostgreSQL jetable et cles JWT temporaires,
  sans acces aux bases ou cles existantes : **146 tests, 1243 assertions**, tous verts.
  Les variables Messenger et les chemins de stockage normalement fournis par Compose
  ont ete renseignes pour ce lancement isole.
- Aucune nouvelle logique applicative : les tests existants verifient le changement
  de runtime, sans ajouter un test qui recopierait les lignes FROM.

## Documentation impactee

`docs/10-operations/deployment.md`.

## Reste a faire hors perimetre

Deploiement sur le VPS, remplacement des processus API et worker, puis verification
par le proxy reel et examen des journaux de production. Aucun deploiement effectue ici.

## Definition de termine

- [x] Images PHP corrigees dans les deux Dockerfiles.
- [x] Constructions et controles CLI/FPM passes.
- [x] Suite complete pre-merge verte.
- [x] Documentation et limites de deploiement consignees.
