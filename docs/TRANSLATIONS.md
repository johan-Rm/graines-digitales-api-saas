# Content translations

The content API uses a local Sylius-style translatable model.

## Model

- Root resources keep identity, project ownership, status, audit fields and non-localized relations.
- `*Translation` resources keep localized editorial fields, `locale`, audit fields and the link to their root resource.
- MVP translatable resources are `WebPage`, `Article`, `CreativeWork`, `MenuItem` and `CategoryCode`.
- `(resource_id, locale)` is unique on every translation table.
- Localized slugs for `WebPage` and `Article` are unique by `project_id`, `slug` and `locale`.

Translations also store `project_id`. The root relation remains authoritative for access control; the duplicated project key is used for database-level localized slug uniqueness and project/locale indexes.

## Locale resolution

- Project source locale defaults to `fr`.
- Project enabled locales default to `["fr"]`.
- Supported MVP locales are `fr`, `en`, `es` and `ar`.
- Client reads may request a locale with `?locale=fr` or `Accept-Language`.
- If the requested translation is missing, reads fall back to the project source locale and expose `requestedLocale`, `locale`, `fallbackLocale` and `fallback`.

Backoffice translation routes do not hide missing translations:

```txt
GET /api/projects/{projectId}/web-pages/{id}/translations
GET /api/projects/{projectId}/web-pages/{id}/translations/{locale}
PUT /api/projects/{projectId}/web-pages/{id}/translations/{locale}
```

Equivalent routes exist for articles, creative works, menu items and category codes.
