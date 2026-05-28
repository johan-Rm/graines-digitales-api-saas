# Content translations

The content API uses a local Sylius-style translatable model.

## Model

- Root resources keep identity, project ownership, status, audit fields and non-localized relations.
- `*Translation` resources keep localized editorial fields, `locale`, audit fields and the link to their root resource.
- MVP translatable resources are `WebPage`, `Article`, `CategoryCode`, `Accommodation` and `MediaObject`.
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
- **Write operations** (PUT/POST) never apply the fallback: the locale from `?locale` or `Accept-Language` is used directly as the write target, creating the translation if it does not exist.

## Single-locale root PUT

The root PUT route for translatable resources accepts localized fields as flat properties for a single locale.
The target locale is resolved from `?locale` or `Accept-Language` (defaults to project source locale).
The `translations` array is not accepted on this route.

```txt
PUT /api/projects/{projectId}/accommodations/{id}?locale=fr
PUT /api/projects/{projectId}/web-pages/{id}?locale=fr
PUT /api/projects/{projectId}/category-codes/{id}?locale=fr
PUT /api/projects/{projectId}/media-objects/{id}?locale=fr
```

## Bulk translation update

A dedicated route replaces all locale translations for an existing resource in one request.
The response embeds the full `translations` collection alongside the root fields.

```txt
PUT /api/projects/{projectId}/accommodations/{id}/translations
PUT /api/projects/{projectId}/web-pages/{id}/translations
PUT /api/projects/{projectId}/category-codes/{id}/translations
PUT /api/projects/{projectId}/media-objects/{id}/translations
```

Payload:

```json
{
  "translations": [
    { "locale": "fr", "name": "...", "body": "..." },
    { "locale": "en", "name": "...", "body": "..." },
    { "locale": "es", "name": "...", "body": "..." }
  ]
}
```

This route replaces all existing translations with the provided set (orphan removal).

## Bulk translation create

A collection-level POST route creates or updates translations for a resource identified by UUID.
Useful for bulk imports and backoffice screens that manage all locales in one request.

```txt
POST /api/projects/{projectId}/accommodations/translations
POST /api/projects/{projectId}/web-pages/translations
POST /api/projects/{projectId}/category-codes/translations
POST /api/projects/{projectId}/media-objects/translations
```

Payload:

```json
{
  "resourceId": "uuid-of-the-resource",
  "translations": [
    { "locale": "fr", "name": "..." },
    { "locale": "en", "name": "..." }
  ]
}
```

## Translation collection read

Each resource exposes a read-only collection of its translations:

```txt
GET /api/projects/{projectId}/accommodations/{id}/translations
GET /api/projects/{projectId}/web-pages/{id}/translations
GET /api/projects/{projectId}/category-codes/{id}/translations
GET /api/projects/{projectId}/media-objects/{id}/translations
GET /api/projects/{projectId}/articles/{id}/translations
```

## Auto-translation

The `PUT /{id}/translations` and `POST /translations` routes trigger automatic translation
after the provided translations are saved.

The source locale is the project source locale (`project.sourceLocale`). For each other
enabled locale, any field that is empty or missing is filled via Google Translate.
Fields that already have a non-empty value are never overwritten.

Fields eligible for auto-translation are declared with `#[AutoTranslate]` on the
`*Translation` entity property:

| Entity                   | Auto-translated fields                                        |
|--------------------------|---------------------------------------------------------------|
| `AccommodationTranslation` | name, label, highlight, body, review, metaTitle, metaDescription |
| `WebPageTranslation`     | headline, body, metaTitle, metaDescription                    |
| `ArticleTranslation`     | headline, body                                                |
| `CategoryCodeTranslation`| label                                                         |
| `MediaObjectTranslation` | caption                                                       |

Adding `#[AutoTranslate]` to any string field of a future `*Translation` entity is
sufficient to include it in the auto-translation pass — no further configuration required.

The `GoogleTranslateClient` can operate in stub mode while production credentials are not
available; it returns the source string unchanged.
