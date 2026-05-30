# TASK-023 — Format compact des relations dans toutes les réponses et payloads

## Statut

En cours

## Contexte

Toutes les réponses API doivent utiliser un format compact homogène pour les relations :

- `CategoryCode` → `codeValue` (ex. `"local-commercial"`, `"bien-a-vendre"`)
- Toute autre ressource liée → UUID (ex. `"019e72cc-..."`)

Actuellement ce n'est pas le cas :

| Route | Format relations observé |
|---|---|
| `GET /accommodations/{id}` (standard AP) | IRIs (ex. `/api/projects/{id}/category-codes/{uuid}`) |
| `PUT /accommodations/{id}` (standard AP) | IRIs dans la réponse |
| `PUT /accommodations/{identifier}` (controller custom) | codeValue/UUID ✓ |
| `PUT /accommodations/{identifier}/translations` (controller custom) | codeValue/UUID ✓ |

Les payloads (POST/PUT) côté standard AP attendent aussi des IRIs, alors que les controllers custom acceptent codeValue/UUID via `resolveCategoryCode`.

## Analyse technique

### Normalisation (réponses)

`ApiResourceNormalizer` décore `api_platform.jsonld.normalizer.item`. Quand AP sérialise une ressource via son pipeline JSON-LD, les relations imbriquées sont converties en **strings IRI** avant que `compactRoot()` ne soit appelé. Or `compactValue()` ne traite que les tableaux (objets embarqués) — les strings IRI passent en l'état.

```
Accommodation → api_platform.jsonld.normalizer.item → { category: "/api/.../category-codes/{uuid}" }
                                                            ↑ string → compactValue retourne tel quel
```

Pour les controllers custom utilisant `$serializer->serialize($obj, 'json')`, le format `json` ne déclenche pas la conversion IRI → les relations arrivent comme tableaux → `compactValue` les compacte correctement (après fix TASK-023-prep).

### Problème CategoryCode IRI → codeValue

Pour les routes standard AP, `basename("/api/.../category-codes/{uuid}")` donne le **UUID**, pas le `codeValue`. Il n'est pas possible d'obtenir le `codeValue` depuis l'IRI sans requête DB.

**Solution retenue** : utiliser `code` comme identifiant IRI de `CategoryCode` à la place de l'UUID. Cela rend `basename($iri)` → `codeValue` et aligne payload et réponse sur le même format.

### Dénormalisation (payloads)

Les routes standard AP attendent des IRIs pour les relations. Il faut un dénormaliseur custom qui transforme les entrées codeValue/UUID en IRI avant que AP ne les traite.

## Périmètre

### Normalisation à corriger

1. **`ApiResourceNormalizer.compactValue`** : ajouter la gestion des strings IRI
   - détecter `/api/` en préfixe → extraire `basename`
   - pour CategoryCode (chemin contient `/category-codes/`) → retourner le code directement (valide après point 2)
   - pour les autres → retourner le UUID (dernier segment du path)

2. **Identifiant IRI de `CategoryCode`** : passer de UUID à `code`
   - Annoter `code` avec `#[ApiProperty(identifier: true)]`
   - Retirer `id` comme identifiant principal de l'API (garder UUID en DB)
   - Vérifier l'impact sur les routes `GET /category-codes/{id}` → devient `GET /category-codes/{code}`
   - Mettre à jour les fixtures et tests

3. **`compactResourceObject`** : supprimer la dépendance à `@type: "CategoryCode"`
   - Avec l'IRI basé sur `code`, `basename` suffit
   - Harmoniser avec la détection sans `@type` (déjà amorcée pour les controllers custom)

### Dénormalisation à corriger

4. **Dénormaliseur custom `ProjectScopedRelationDenormalizer`** :
   - Intercepte les opérations POST/PUT sur les ressources project-scoped
   - Pour chaque champ de relation connu (`category`, `realEstateListing`, `place`, `amenityFeature`, `tags`, `associatedMedia.mediaObject`, etc.) :
     - Si valeur = string non-IRI sans `/` → traiter comme codeValue (CategoryCode) ou UUID (autres)
     - Convertir en IRI avant passage à AP : `/api/projects/{projectId}/category-codes/{code}` ou `/api/projects/{projectId}/media-objects/{uuid}`
   - Priorité haute pour ne pas interférer avec le dénormaliseur AP

5. **Uniformiser `DashboardAccommodationByIdentifierController`** : s'appuyer sur le dénormaliseur plutôt que sur `resolveCategoryCode` manuel (refactor non bloquant, peut être traité post-déploiement)

### Champs à nettoyer dans les réponses

6. **`ProjectOwned` trait** : `project` est exposé via `#[Groups(['read'])]` → retirer du groupe `read` ou le compacter à UUID (le projet est déjà dans l'URL, c'est redondant)

7. **`AuditTrail` trait** : `createdBy` / `updatedBy` sont des `User` → actuellement compactés à UUID après fix, ce qui est acceptable ; vérifier que c'est cohérent

## Ordre d'exécution recommandé

```
1. Changer l'identifiant IRI de CategoryCode (code au lieu de UUID)
2. Mettre à jour ApiResourceNormalizer.compactValue pour gérer les strings IRI
3. Vérifier les réponses GET standard → codeValue/UUID attendus
4. Implémenter ProjectScopedRelationDenormalizer
5. Vérifier les payloads POST/PUT → codeValue/UUID acceptés
6. Nettoyer project/updatedBy dans les réponses (retirer du groupe read ou laisser UUID)
7. Mettre à jour les fixtures et tests fonctionnels
8. Documenter dans docs/03-api-contracts/
```

## Critères d'acceptation

- `GET /api/projects/{id}/accommodations/{id}` → `"category": "local-commercial"` (codeValue)
- `GET /api/projects/{id}/accommodations/{id}` → `"realEstateListing": "bien-a-vendre"` (codeValue)
- `GET /api/projects/{id}/accommodations/{id}` → `"amenityFeature": ["piscine", "jardin"]` (array de codeValues)
- `GET /api/projects/{id}/accommodations/{id}` → `"associatedMedia": [{ "mediaObject": "uuid", ... }]`
- `PUT /api/projects/{id}/accommodations/{id}` avec `"category": "local-commercial"` → 200 ✓
- `PUT /api/projects/{id}/accommodations/{id}` avec `"mediaObject": "uuid"` → 200 ✓
- Même comportement sur WebPage, Article, CategoryCode, MediaObject
- Suite de tests fonctionnels verte

## Risques

- **Breaking change** : les IRIs CategoryCode changent de format → tout client qui stocke des IRIs CategoryCode doit être mis à jour
- Les `uriVariables` des routes CategoryCode (ex. `category-codes/{id}`) pointent vers `id` → à changer en `code`
- Les migrations de données ne sont pas nécessaires (UUID reste en DB) mais les indexes et contraintes d'unicité sur `code` doivent être vérifiés (déjà unique par `project_id + code`)
