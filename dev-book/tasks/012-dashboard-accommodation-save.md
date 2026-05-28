# Task : Sauvegarde dashboard des accommodations par identifiant

## Metadonnees

- ID : TASK-012
- Source : besoin dashboard Nuxt du 2026-05-21
- Statut : Terminee
- Cloture : 2026-05-22
- Perimetre : API Symfony, dashboard Nuxt, Accommodation, CategoryCode
- Dependances fonctionnelles : TASK-001, TASK-002, TASK-008

## Objectif

Permettre au dashboard Nuxt de sauvegarder un bien immobilier identifie par son
`identifier` metier, par exemple `BAVLC001`, sans connaitre a l'avance l'UUID Doctrine de
l'`Accommodation`.

## Contrat MVP

- Seeder le `CategoryCode` manquant `local-commercial` dans `accommodation-type` et
  completer les jeux de codes utilises par le dashboard immobilier.
- Confirmer que les endpoints projet existent :
  - `GET /api/projects/{projectId}/category-codes`
  - `GET /api/projects/{projectId}/accommodations`
  - `PUT /api/projects/{projectId}/accommodations/{uuid}?locale=fr`
  - `POST /api/projects/{projectId}/accommodations?locale=fr`
- Ajouter une route de sauvegarde par identifiant bornee par projet :
  - `PUT /api/projects/{projectId}/accommodations/{identifier}?locale=fr`
- La route resout l'identifier uniquement dans le projet demande, verifie
  l'acces en ecriture, puis persiste la mise a jour en conservant les memes invariants
  metier que les routes projet.

## Process de traduction automatique

La route dashboard conserve le contrat item actuel :

```txt
PUT /api/projects/{projectId}/accommodations/{identifier}?locale={locale}
```

L'`identifier` est commun a toutes les langues. Chaque appel cible une locale via le
parametre `locale`.

Regles attendues pour le process de traduction :

- Si l'API recoit uniquement la traduction source, par exemple `fr`, les autres
  traductions attendues du projet peuvent etre creees automatiquement a partir de cette
  source.
- Si l'API recoit plusieurs objets de traduction, par exemple `fr`, `en` et `es`, le
  process automatique ne doit traduire que les champs inexistants, `null` ou vides.
- Des qu'un champ traduit contient une valeur non vide, cette valeur est consideree comme
  une traduction personnalisee par l'utilisateur et ne doit pas etre ecrasee par la
  traduction automatique.
- Les champs metier globaux de l'`Accommodation` restent communs a toutes les locales.
  Les automatisations de traduction doivent se limiter aux champs localises.
- L'API utilisera Google Translate pour produire les traductions automatiques.
  Tant que la configuration definitive n'est pas disponible, le client Google Translate
  peut fonctionner en mode fictif et retourner la chaine source pour valider le contrat
  de sauvegarde.

Champs localises concernes :

- `slug`
- `name`
- `label`
- `highlight`
- `body`
- `review`
- `metaTitle`
- `metaDescription`

Note dashboard frontend :

- Continuer a appeler `PUT /api/projects/{projectId}/accommodations/{identifier}?locale={locale}`.
- Pour une sauvegarde mono-locale, envoyer les champs localises a la racine du payload.
- Pour une sauvegarde multilingue, envoyer un tableau `translations` avec un objet par
  locale.
- Ne pas renvoyer une valeur personnalisee non vide si l'utilisateur veut que l'API la
  regenere automatiquement : envoyer le champ absent, `null` ou vide.
- Envoyer uniquement les champs modifies et les traductions concernees pour limiter les
  ecrasements involontaires.

## Plan d'execution

- [x] Verifier les routes API Platform existantes.
- [x] Verifier le seed `local-commercial`.
- [x] Ajouter la route dashboard par `identifier`.
- [x] Ajouter les tests fonctionnels de sauvegarde dashboard.
- [x] Valider `lint:container` et PHPUnit cible.
- [x] Valider la suite PHPUnit complete.

## Validation

```bash
docker compose exec -T api php bin/console lint:container
docker compose exec -T api php bin/console doctrine:schema:validate
docker compose exec -T api php bin/console app:fixtures:load-demo
docker compose exec -T api php bin/phpunit tests/Functional/DashboardAccommodationSaveTest.php
docker compose exec -T api php bin/phpunit
```

## Note de cloture du 2026-05-24

Le workflow de sauvegarde et traduction automatique est valide pour la route dashboard
`Accommodation`.

La generalisation du meme comportement a toutes les ressources traduisibles est sortie du
scope de cette task et formalisee dans `TASK-014`.

## Points de vigilance

- Securite : ne pas exposer un bien hors membership projet.
- Contrat : conserver les endpoints projet UUID comme source canonique.
- Donnees : le dashboard doit continuer a resoudre les `CategoryCode` en IRI avant envoi.
