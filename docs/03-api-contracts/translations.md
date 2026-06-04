# Translation routes

Translation routes follow the same project-scoped URL prefix as all other resources.
See [`04-domain-model/translations.md`](../04-domain-model/translations.md) for the model, locale resolution and auto-translation rules.

## Single-locale root PUT

The root PUT route for translatable resources accepts localized fields as flat properties for a single locale.
The target locale is resolved from `?locale` or `Accept-Language` (defaults to project source locale).
The `translations` array is not accepted on this route.

```txt
PUT /api/projects/{projectId}/accommodations/{id}?locale=fr
PUT /api/projects/{projectId}/web-pages/{id}?locale=fr
PUT /api/projects/{projectId}/media-objects/{id}?locale=fr
```

`CategoryCode` identity is immutable after creation: `codeValue` and localized `label`
cannot be modified through update routes. The localized descriptive `text` field remains
editable through bulk translation `PUT`.

## Bulk translation update

A dedicated route replaces all locale translations for an existing resource in one request.
The response embeds the full `translations` collection (see [Response shape](#with-translations-response-shape)).

```txt
PUT /api/projects/{projectId}/accommodations/{id}/translations
PUT /api/projects/{projectId}/accommodations/{identifier}/translations  ← by business identifier (non-UUID)
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

## With-translations response shape

Every route that returns the full `translations` collection (`GET`, `PUT` and `POST` on the
`/translations` sub-resource) shares the same envelope. The root object carries **only the
non-localized data** — identity (`identifier`, `id`), unlocalized relations (`category`,
`place`, `associatedMedia`, `qualities`, `offer`, …), audit fields and `project` — plus the
`translations` array. Each localized value lives inside its locale entry in `translations`.

The root **does not** flatten a single resolved locale: the per-locale content fields
(`slug`, `name`, `label`, `highlight`, `body`, `review`, `metaTitle`, `metaDescription`,
`text` for `CategoryCode`) and the locale-resolution metadata (`locale`, `requestedLocale`,
`fallback`, `fallbackLocale`) are **absent** from the root. To read a given locale, look it
up in `translations` by its `locale` key.

```json
{
  "@id": ".../accommodations/{id}/translations",
  "identifier": "BAVLC002",
  "category": "local-commercial",
  "associatedMedia": [ "..." ],
  "translations": [
    { "locale": "fr", "slug": "...", "name": "...", "body": "..." },
    { "locale": "en", "slug": "...", "name": "...", "body": "..." },
    { "locale": "es", "slug": "...", "name": "...", "body": "..." }
  ]
}
```

This contrasts with the **single-locale** routes (`GET`/`PUT` without the `/translations`
suffix), which resolve one locale, flatten its fields at the root and expose
`locale`/`requestedLocale`/`fallback` — and omit the `translations` array.

This shaping is applied centrally in `ApiResourceNormalizer` (`stripLocalizedRootFields`),
keyed on the `read:with-translations` serialization group, so it stays consistent across all
translatable resources.
