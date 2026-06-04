# Accommodation business rules

## Identifier generation

The `identifier` field is a server-generated business key. It is computed by
`AccommodationIdentifierGenerator` when the accommodation has a `project`,
`realEstateListing` and `category`. It is never editable by the client.

Format: `INITIALS(realEstateListing) + INITIALS(category) + counter (min 3 digits)`

Initials are computed by taking the first letter of each `-`-separated word and uppercasing the result.
The counter is incremental per project per `(realEstateListing, category)` prefix and never resets.

| realEstateListing        | category             | identifier  |
|--------------------------|----------------------|-------------|
| `bien-a-vendre`          | `appartement`        | `BAVA001`   |
| `bien-a-vendre`          | `maison-de-campagne` | `BAVMDC001` |
| `location-longue-duree`  | `appartement`        | `LLDA001`   |
| `location-saisonniere`   | `villa-golf`         | `LSVG001`   |

Implementation: `src/State/AccommodationIdentifierGenerator.php`

## Slug generation

The `slug` field on `AccommodationTranslation` is always recomputed server-side via
`recomputeSlug()` after every write. It is never stored directly from client input.

Format:

```text
slug = slugify(identifier + " " + name     + " " + place)   # when name is set
slug = slugify(identifier + " " + category + " " + place)   # when name is absent
```

The identifier is placed first to form a canonical, unambiguous URL prefix.
The place is appended last to add geographic context without affecting uniqueness.
Uniqueness is enforced at the database level by `(project_id, slug, locale)`.

| identifier | name / fallback           | place          | slug                                           |
|------------|---------------------------|----------------|------------------------------------------------|
| `LLDA001`  | `Appartement avec balcon` | `centre-ville` | `llda001-appartement-avec-balcon-centre-ville` |
| `LSVG001`  | —                         | `golf-mogador` | `lsvg001-villa-golf-golf-mogador`              |
| `BAVD001`  | `Superbe local commercial`| `nouvelle-ville`| `bavd001-superbe-local-commercial-nouvelle-ville` |

Slugify rules: ASCII-only, lowercase, apostrophes stripped, spaces and separators replaced
by `-`, non-alphanumeric characters removed, consecutive dashes collapsed.

Implementation: `src/Entity/AccommodationTranslation::recomputeSlug()`
