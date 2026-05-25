# Task : Slug mécanique des Accommodations

## Metadonnees

- ID : TASK-019
- Source : règle métier 3.2 — slugs des fiches accommodation — 2026-05-25
- Statut : Fait
- Périmètre : `AutoSlug`, `AccommodationTranslation`, `Accommodation`, processors, controller, auto-translator
- Dépendances fonctionnelles : TASK-011 (slug generation), TASK-012 (dashboard save), TASK-014 (auto-translation)

## Objectif

Le slug d'une fiche accommodation était généré une seule fois depuis `name`, sans suffixe
de garantie d'unicité, et pouvait être traduit via Google Translate vers d'autres locales.

La règle 3.2 impose une dérivation mécanique et systématique :

```
si name renseigné  : slug = slugify(name + " " + place.code + " " + identifier)
si name absent     : slug = slugify(category.code + " " + place.code + " " + identifier)
```

`identifier` est toujours suffixe pour garantir l'unicité inter-projets.

### Règles de slugify

- minuscules uniquement
- suppression des accents (translitération ASCII)
- suppression des apostrophes (droites et typographiques)
- remplacement des espaces et séparateurs par `-`
- suppression des caractères non alphanumériques
- réduction des tirets multiples
- suppression des tirets en début et fin

### Exemples

| name | place.code | identifier | slug produit |
|---|---|---|---|
| `Appartement avec balcon au centre-ville` | `centre-ville` | `LLDA001` | `appartement-avec-balcon-au-centre-ville-centre-ville-llda001` |
| `Villa golf résidentielle à Mogador` | `golf-mogador` | `LLDVG001` | `villa-golf-residentielle-a-mogador-golf-mogador-lldvg001` |
| _(absent)_ | `golf-mogador` | `LSVG001` | `villa-golf-golf-mogador-lsvg001` |

## Changements réalisés

### `src/Entity/Traits/AutoSlug.php`

Ajout de `buildSlug(string ...$parts): string` : joint les parties non-vides, supprime
les apostrophes, passe dans `normalizeSlug()`.

### `src/Entity/AccommodationTranslation.php`

- `setName()` : suppression de la génération automatique du slug
- `recomputeSlug()` : nouveau — calcule le slug depuis `translatable` (name|category.code,
  place.code, identifier) ; no-op si `translatable` non encore défini
- `slug` : retiré du groupe `write` — lecture seule via l'API

### `src/Entity/Accommodation.php`

- `slug` : retiré du groupe `write` — lecture seule via l'API

### `src/State/ProjectScopedPersistProcessor.php`

Appel de `recomputeSlug()` sur chaque `AccommodationTranslation` après `setTranslatable()`,
avant `persist()`. Couvre `POST /accommodations` et `PUT /accommodations/{id}`.

### `src/State/ProjectScopedTranslationPersistProcessor.php`

Idem. Couvre `PUT /accommodations/{id}/translations/{locale}`.

### `src/Controller/DashboardAccommodationByIdentifierController.php`

- Boucle finale des translations : `instanceof AccommodationTranslation` unique, appel de
  `recomputeSlug()` après `setTranslatable()`
- `applyPayload()` : champ `slug` retiré — plus transmissible par le client

### `src/Translation/AccommodationAutoTranslator.php`

- `slug` retiré de `LOCALIZED_FIELDS` — le slug n'est plus envoyé à Google Translate
- `applyLocalizedFields()` simplifié (guard `slug` supprimé)

## Validation

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

## Points de vigilance

- Le slug est recalculé à chaque persist — un changement de `name`, `place` ou `identifier`
  modifie le slug de toutes les locales au prochain enregistrement.
- `place` et `category` doivent être définis sur l'accommodation avant le premier persist
  d'une translation, sinon les parties manquantes produisent un slug partiel.
- La contrainte unique `(project_id, slug, locale)` reste en place : si deux fiches
  produisent le même slug mécanique (ex. même name + même place + même identifier), une
  violation SQL remonte en 500. L'`identifier` étant unique par projet, ce cas ne devrait
  pas se produire en pratique.
- Côté frontends : `slug` n'est plus un champ `write` dans les payloads — les clients qui
  l'envoyaient doivent le supprimer de leurs requêtes.
