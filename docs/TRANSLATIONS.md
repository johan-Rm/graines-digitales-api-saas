# Content translations

The content API uses a local Sylius-style translatable model.

## Model

- Root resources keep identity, project ownership, status, audit fields and non-localized relations.
- `*Translation` resources keep localized editorial fields, `locale`, audit fields and the link to their root resource.
- MVP translatable resources are `WebPage`, `Article`, `CategoryCode`,
  `Accommodation` and `MediaObject`.
- `(resource_id, locale)` is unique on every translation table.
- Localized slugs for `WebPage` and `Article` are unique by `project_id`, `slug` and `locale`.

Translations also store `project_id`. The root relation remains authoritative for access control; the duplicated project key is used for database-level localized slug uniqueness and project/locale indexes.

## Locale resolution

- Project source locale defaults to `fr`.
- Project enabled locales default to `["fr", "en", "es"]`.
- Supported MVP locales are `fr`, `en`, `es` and `ar`.
- Client reads may request a locale with `?locale=fr` or `Accept-Language`.
- If the requested translation is missing, reads fall back to the project source locale and expose `requestedLocale`, `locale`, `fallbackLocale` and `fallback`.
- Standard API reads expose resolved localized fields directly on the root resource and hide the technical `translations` collection.

Backoffice translation routes do not hide missing translations:

```txt
GET /api/projects/{projectId}/web-pages/{id}/translations
GET /api/projects/{projectId}/web-pages/{id}/translations/{locale}
PUT /api/projects/{projectId}/web-pages/{id}/translations/{locale}
```

Equivalent routes exist for articles and category codes.
Media object translation routes expose localized captions, used as image alt text:

```txt
GET /api/projects/{projectId}/media-objects/{id}/translations
GET /api/projects/{projectId}/media-objects/{id}/translations/{locale}
PUT /api/projects/{projectId}/media-objects/{id}/translations/{locale}
```

Accommodation translations also expose a project-level collection route for
backoffice screens that need every accommodation locale in one call:

```txt
GET /api/projects/{projectId}/accommodation-translations
```

This collection returns translations for non-archived accommodations in the
requested project only, with the parent accommodation UUID exposed as
`accommodation`.
