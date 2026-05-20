# Fixtures de demonstration

Les fixtures locales creent un jeu de donnees stable pour le developpement et les
tests fonctionnels de l'API multi-clients.

## Chargement

```bash
docker compose exec api php bin/console app:fixtures:load-demo
```

La commande est idempotente : elle peut etre relancee sans dupliquer les clients,
projets, utilisateurs, memberships et contenus de demonstration.

## Donnees creees

- 2 organisations clientes.
- 3 projets immobiliers rattaches aux organisations.
- 3 utilisateurs de demonstration.
- Des memberships explicites par projet.
- Un socle de contenus par projet : page, article, menu et categorie.

## Comptes de demonstration

Tous les comptes utilisent le mot de passe local suivant :

```txt
password123
```

Comptes :

- `admin@grainesdigitales.local` : administrateur global et owner des projets.
- `editor.essaouira@grainesdigitales.local` : editeur des projets Essaouira Patrimoine Immobilier.
- `viewer.mogador@grainesdigitales.local` : lecteur du projet Blue Bay Mogador.

