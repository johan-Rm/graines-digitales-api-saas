# Task : Champs publics de profil organisation

## Metadonnees

- ID : TASK-017
- Source : demande produit du 2026-05-25
- Statut : En cours
- Perimetre : Organization, contrats API, fixtures demo
- Dependances fonctionnelles : TASK-001, TASK-004, TASK-011

## Objectif

Enrichir `Organization` avec les informations publiques necessaires au profil MLK et aux
contrats consommes par les frontends : acronyme, nom complet, alias, localisation, image,
emails, telephones et description.

## Contrat MVP

Ajouter a `Organization` :

- `acronym`
- `fullName`
- `alternateName`
- `location`
- `image`
- `email` comme liste de chaines
- `phoneNumbers` comme liste de chaines
- `description`

Les nouveaux champs restent optionnels pour conserver la compatibilite avec les payloads
existants qui ne fournissent que `name`.

## Plan d'execution

- [x] Ajouter les champs sur l'entite `Organization`.
- [x] Ajouter une migration Doctrine reversible.
- [x] Renseigner les fixtures demo MLK.
- [x] Aligner les contrats YAML partages.
- [x] Ajouter un test fonctionnel de lecture/ecriture API.

## Validation

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

## Points de vigilance

- Contrat API : `email` et `phoneNumbers` sont des listes, pas des scalaires.
- Securite : aucun changement sur l'isolation projet ou les guards d'acces.
- Migration : champs optionnels et valeurs JSON par defaut pour ne pas bloquer les lignes
  existantes.
