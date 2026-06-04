# Translation model

The content API uses a local Sylius-style translatable model.

## Model

- Root resources keep identity, project ownership, status, audit fields and non-localized relations.
- `*Translation` resources keep localized editorial fields, `locale`, audit fields and the link to their root resource.
- MVP translatable resources are `WebPage`, `Article`, `CategoryCode`, `Accommodation` and `MediaObject`.
- `(resource_id, locale)` is unique on every translation table.
- Localized slugs for `WebPage` and `Article` are unique by `project_id`, `slug` and `locale`.

Translations also store `project_id`. The root relation remains authoritative for access control; the duplicated project key is used for database-level localized slug uniqueness and project/locale indexes.

## Locale resolution

- Each project defines its own source locale and enabled locales, stored in the database.
- The source locale is used as the fallback when a requested translation is missing.
- Client reads may request a locale with `?locale=fr` or `Accept-Language`.
- If the requested translation is missing, reads fall back to the project source locale and expose `requestedLocale`, `locale`, `fallbackLocale` and `fallback`.
- Standard API reads expose resolved localized fields directly on the root resource and hide the technical `translations` collection.
- **Write operations** (PUT/POST) never apply the fallback: the locale from `?locale` or `Accept-Language` is used directly as the write target, creating the translation if it does not exist.

## Auto-translation

The `PUT /{id}/translations` and `POST /translations` routes trigger automatic translation
after the provided translations are saved. The full pipeline for these routes is:

1. **User data** — translations provided in the request body are applied first.
2. **Auto-translation** — for each enabled locale other than the source locale, any field
   that is absent (no translation object) or empty is filled via Google Translate.
   A translation object is created for the locale if it does not exist yet.
   Fields that already have a non-empty value are never overwritten.
3. **Auto-fill** — a second pass derives still-empty fields from other fields on the same
   translation (e.g. `slug` from `headline`, `metaTitle` from `name`).

The source locale is the project source locale (`project.sourceLocale`).

Fields eligible for auto-translation are declared with `#[AutoTranslate]` on the
`*Translation` entity property. The source locale field must be non-empty for translation
to proceed; auto-translated fields on non-source locales may be empty before this pass runs
and must therefore not carry a `NotBlank` constraint.

| Entity                     | Auto-translated fields                                              |
|----------------------------|---------------------------------------------------------------------|
| `AccommodationTranslation` | name, label, highlight, body, review, metaTitle, metaDescription    |
| `WebPageTranslation`       | headline, body, metaTitle, metaDescription                          |
| `ArticleTranslation`       | headline, body                                                      |
| `CategoryCodeTranslation`  | label, text                                                         |
| `MediaObjectTranslation`   | caption                                                             |

Adding `#[AutoTranslate]` to any string field of a future `*Translation` entity is
sufficient to include it in the auto-translation pass — no further configuration required.

The `GoogleTranslateClient` can operate in stub mode while production credentials are not
available; it returns the source string unchanged.

## Auto-fill

After auto-translation, a second pass fills fields that are still empty by deriving their
value from another field on the same translation. This covers slug, metaTitle and
metaDescription without any manual input.

Fields are declared with `#[AutoFill(from: 'fieldName', maxLength: ?int)]`:

| Entity                     | Field             | Derived from | Max length |
|----------------------------|-------------------|--------------|------------|
| `AccommodationTranslation` | `metaTitle`       | `name`       | —          |
| `AccommodationTranslation` | `metaDescription` | `body` (premier paragraphe markdown) | 160 |
| `WebPageTranslation`       | `slug`            | `headline`   | —          |
| `WebPageTranslation`       | `metaTitle`       | `headline`   | —          |
| `WebPageTranslation`       | `metaDescription` | `body`       | 160        |
| `ArticleTranslation`       | `slug`            | `headline`   | —          |

Execution order per request: user data → auto-translation → auto-fill → recomputeSlug (Accommodation only, see [`accommodations.md`](accommodations.md)).

A non-empty existing value is never overwritten. Adding `#[AutoFill]` to a field on any
`*Translation` entity is sufficient — no further configuration required.
