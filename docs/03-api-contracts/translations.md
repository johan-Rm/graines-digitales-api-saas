# Translation routes

Translation routes follow the same project-scoped URL prefix as all other resources.
See [`04-domain-model/translations.md`](../04-domain-model/translations.md) for the model, locale resolution and auto-translation rules.

## Contract summary

Translatable resources expose two complementary route families:

| Route family                  | Purpose                          | Payload shape                                      | Response shape                                      |
|-------------------------------|----------------------------------|----------------------------------------------------|-----------------------------------------------------|
| without `/translations`       | read or write one resolved locale | localized fields are flat root fields              | localized fields are flat root fields               |
| with `/translations`          | read or write all managed locales | localized fields live in `translations[]` entries  | localized fields live in `translations[]` entries   |

The standard translatable resources are `Accommodation`, `Article`, `WebPage`,
`CategoryCode` and `MediaObject`.

Implemented route matrix:

| Resource        | Root `GET` | Root `POST` | Root `PUT` | `GET /translations` | `PUT /translations` | `POST /translations` |
|-----------------|------------|-------------|------------|---------------------|---------------------|----------------------|
| `Accommodation` | yes        | yes         | yes        | yes                 | yes                 | yes                  |
| `Article`       | yes        | yes         | yes        | yes                 | yes                 | yes                  |
| `WebPage`       | yes        | yes         | yes        | yes                 | yes                 | yes                  |
| `MediaObject`   | yes        | yes         | yes        | yes                 | yes                 | yes                  |
| `CategoryCode`  | yes        | yes         | no         | yes                 | yes                 | yes                  |

`CategoryCode` is the only standard exception: its root identity
(`inCodeSet`, `codeValue`, source label) is immutable after creation, so localized
descriptive fields are updated through `/translations`.

`Accommodation` also exposes dashboard convenience routes by business identifier for
non-UUID identifiers:

```txt
PUT /api/projects/{projectId}/accommodations/{identifier}?locale=fr
GET /api/projects/{projectId}/accommodations/{identifier}/translations
PUT /api/projects/{projectId}/accommodations/{identifier}/translations
```

## Single-locale root PUT

The root PUT route for translatable resources accepts localized fields as flat properties for a single locale.
The target locale is resolved from `?locale` or `Accept-Language` (defaults to project source locale).
The `translations` array is not accepted on this route.

```txt
PUT /api/projects/{projectId}/accommodations/{id}?locale=fr
PUT /api/projects/{projectId}/articles/{id}?locale=fr
PUT /api/projects/{projectId}/web-pages/{id}?locale=fr
PUT /api/projects/{projectId}/media-objects/{id}?locale=fr
```

`CategoryCode` identity is immutable after creation: `codeValue` and localized `label`
cannot be modified through update routes. The localized descriptive `text` field remains
editable through bulk translation `PUT`.

For `MediaObject` uploads, `caption` is optional on both multipart creation routes:
`POST /media-objects` and `POST /media-objects/translations`. If it is absent or blank,
the API generates a readable caption from the original filename, for example
`setup_ia-example.jpg` becomes `Setup ia example`. On `/translations`, this generated
caption is used as the source value before automatic translation runs.

## Bulk translation update

A dedicated route updates several locale translations for an existing resource in one request.
The response embeds the full `translations` collection (see [Response shape](#with-translations-response-shape)).

```txt
PUT /api/projects/{projectId}/accommodations/{id}/translations
PUT /api/projects/{projectId}/accommodations/{identifier}/translations  ← by business identifier (non-UUID)
PUT /api/projects/{projectId}/articles/{id}/translations
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

This route upserts the provided locale entries. Existing translations for omitted locales
are not deleted. After user data is applied, auto-translation may create or complete missing
enabled locales from the project source locale.

## Bulk translation create or apply

A collection-level POST route creates a resource with several translations in one request.
For resources handled by the generic translation processor (`Article`, `WebPage`,
`CategoryCode`, `MediaObject`), sending `resourceId` applies the provided translations to
an existing resource instead of creating a new one. `Accommodation` uses a dedicated
creation processor; update an existing accommodation through `PUT /accommodations/{id}/translations`
or `PUT /accommodations/{identifier}/translations`.

```txt
POST /api/projects/{projectId}/accommodations/translations
POST /api/projects/{projectId}/articles/translations
POST /api/projects/{projectId}/web-pages/translations
POST /api/projects/{projectId}/category-codes/translations
POST /api/projects/{projectId}/media-objects/translations
```

Create payload:

```json
{
  "translations": [
    { "locale": "fr", "name": "..." },
    { "locale": "en", "name": "..." }
  ]
}
```

Generic apply payload for an existing resource:

```json
{
  "resourceId": "uuid-of-the-resource",
  "translations": [
    { "locale": "fr", "headline": "..." },
    { "locale": "en", "headline": "..." }
  ]
}
```

## Translation collection read

Each resource exposes a read-only collection of its translations:

```txt
GET /api/projects/{projectId}/accommodations/{id}/translations
GET /api/projects/{projectId}/articles/{id}/translations
GET /api/projects/{projectId}/web-pages/{id}/translations
GET /api/projects/{projectId}/category-codes/{id}/translations
GET /api/projects/{projectId}/media-objects/{id}/translations
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
