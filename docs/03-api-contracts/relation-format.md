# Format compact des relations

## Principe

Toutes les réponses et payloads API utilisent un format compact homogène pour les relations :

| Type de relation | Format réponse | Format payload accepté |
|---|---|---|
| `CategoryCode` | `codeValue` (`"local-commercial"`) | `codeValue` ou UUID |
| Toute autre ressource liée | UUID (`"019e72cc-..."`) | UUID |

Ce format s'applique à toutes les routes, qu'elles passent par le pipeline standard d'API Platform ou par un controller custom.

## Réponses (normalisation)

`ApiResourceNormalizer` post-traite chaque réponse sérialisée :

- **Objets imbriqués** (custom controllers avec `serialize('json')`) : détectés via UUID `id` ou `@type` JSON-LD, puis compactés.
- **Strings IRI** (pipeline JSON-LD standard d'AP) : détectées via préfixe `/api/`, puis compactées via `compactIri()`.
  - `/api/projects/{pid}/category-codes/{uuid}` → résolution entity depuis l'identity map Doctrine → `codeValue`
  - Toute autre IRI → UUID extrait du dernier segment de path

Les types dans `EMBEDDED_TYPES` (`AccommodationMedia`, `PropertyValue`, `WebPageMedia`) restent des objets complets dans la réponse ; leurs propres relations imbriquées sont compactées.

Le champ `project` est exclu du groupe `read` (redondant avec l'URL).

## Payloads (dénormalisation)

`ProjectScopedRelationDenormalizer` intercepte les opérations POST/PUT sur les `ProjectScopedResource` avant le dénormaliseur API Platform. Il convertit les valeurs compactes en IRIs que AP peut résoudre :

```json
{
  "category": "local-commercial",
  "realEstateListing": "bien-a-vendre",
  "place": "bouzama",
  "amenityFeature": ["chateau-d-eau", "alarme"],
  "associatedMedia": [
    { "position": 1, "mediaObject": "019e72cc-08c0-7e1b-b1f9-d87b1efe15a6", "caption": "..." }
  ]
}
```

Règles de résolution :

- **`CategoryCode`** : si la valeur est un codeValue (sans `/`) → `findOneBy(project, code)` ; si UUID → `find(id)` → IRI via `IriConverter`
- **Autres `ProjectScopedResource`** : si UUID → `find(id)` → IRI via `IriConverter`
- **IRI déjà valide** (commence par `/`) → passée telle quelle
- **Valeur non résoluble** → passée telle quelle (AP lève une erreur de dénormalisation)

Le dénormaliseur est récursif : les objets imbriqués dans des collections (ex. items `associatedMedia`) ont eux aussi leurs relations traitées.

## Exemple de réponse complète

```json
{
  "id": "019e72cc-0a77-79bc-abdc-d84698369572",
  "identifier": "BAVLC001",
  "slug": "local-commercial-bouzama-bavlc001",
  "locale": "fr",
  "name": "Local commercial Erraounak",
  "label": "Local commercial en médina",
  "status": "published",
  "isActive": true,
  "category": "local-commercial",
  "realEstateListing": "bien-a-vendre",
  "place": "bouzama",
  "amenityFeature": ["chateau-d-eau", "alarme"],
  "tags": ["immobilier"],
  "offer": { "price": "450000", "priceCurrency": "EUR" },
  "associatedMedia": [
    {
      "id": "019e72cc-0a7f-7208-ae63-37e66cad15df",
      "position": 1,
      "caption": "Espace intérieur",
      "keywords": ["gallery"],
      "mediaObject": "019e72cc-08c0-7e1b-b1f9-d87b1efe15a6"
    }
  ],
  "qualities": [
    { "id": "...", "name": "Confort", "value": 4 }
  ],
  "createdAt": "2026-05-29T08:13:55+00:00",
  "updatedBy": "019e72cb-a30b-7b73-82b2-c8f146ce31a3"
}
```
