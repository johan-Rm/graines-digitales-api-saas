# Fixtures de demonstration

Les fixtures locales creent un jeu de donnees stable pour le developpement de
l'API dashboard MLK.

## Chargement

```bash
docker compose exec api php bin/console app:fixtures:load-demo
```

La commande est idempotente : elle peut etre relancee sans dupliquer
l'organisation, le projet, les utilisateurs, les memberships et les contenus de
demonstration.

## Donnees creees

- 1 organisation cliente : `MLK - My Little Kasbah`.
- 1 projet immobilier : `MLK - Modern Site Web`, slug `mlk-modern-site-web`.
- 2 utilisateurs de demonstration.
- Des memberships explicites sur le projet MLK.
- Un socle de contenus pour le projet : page, article, menu et categorie.
- 5 biens immobiliers de demonstration charges comme `Accommodation`, avec
  relations SQL vers `CategoryCode` et `MediaObject`.

Les biens immobiliers utilisent les referentiels `CategoryCode` suivants :

- `accommodation-type` pour le type de bien.
- `real-estate-listing` pour le type d'annonce.
- `accommodation-place` pour le lieu.
- `amenity-feature` pour les equipements.
- `tag` pour les tags editoriaux.

## Comptes de demonstration

- `johan.remy@graines-digitales.online` : administrateur global et owner du projet, mot de passe `password1234`.
- `buzac@mlk-my-little-kasbah.immo` : administratrice du projet, mot de passe `password123`.
