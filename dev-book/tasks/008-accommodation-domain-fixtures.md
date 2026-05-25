# Task : Entite Accommodation et fixtures immobilieres relationnelles

## Metadonnees

- ID : TASK-008
- Source : demande utilisateur du 2026-05-21
- Statut : Implemente
- Perimetre : API Symfony 8 / API Platform / Doctrine / fixtures demo immobilieres
- Dependances : TASK-001, TASK-002, TASK-004
- Source schema cible : `/home/johan/www/graines-digitales/modern-web-apps/mlk-my-little-kasbah/schemas/interfaces/accommodation.ts`

## Constat

Les 5 biens immobiliers de demonstration sont actuellement charges comme `CreativeWork`
avec un payload `schemaData` dont le type est `Accommodation`.

Cette approche est utile pour prototyper rapidement les donnees, mais elle ne respecte pas
l'objectif long terme de TASK-001 : garder le coeur metier en SQL structure et reserver
JSONB aux donnees secondaires ou evolutives.

Le besoin valide est de charger ces biens via une vraie entite `Accommodation`, avec ses
relations metier, afin de pouvoir filtrer, tester, exposer et maintenir les annonces
immobilieres proprement.

## Objectif

Introduire un modele `Accommodation` project-scoped et translatable pour les biens
immobiliers, puis adapter les fixtures demo afin que les 5 biens soient crees via cette
entite et ses relations au lieu d'etre stockes comme `CreativeWork`.

Le modele doit rester compatible avec le schema webapp existant, sans chercher a implementer
tout le domaine immobilier long terme en une seule fois.

## Perimetre V1

- Creer une entite racine `Accommodation` rattachee a `Project`.
- Creer une entite `AccommodationTranslation` pour les champs localises.
- Exposer une route projet lisible, par exemple :
  - `GET /api/projects/{projectId}/accommodations`
  - `GET /api/projects/{projectId}/accommodations/{id}`
- Garder les regles existantes de tenancy, JWT, membership et soft delete.
- Migrer les 5 fixtures immobilieres actuelles depuis `CreativeWork` vers `Accommodation`.
- Documenter le nouveau chargement de fixtures.

## Donnees structurees attendues

### Racine `Accommodation`

- `identifier`
- `project`
- `status`
- `publishedAt`
- `deletedAt`
- champs d'audit
- caracteristiques non localisees :
  - `yearBuilt`
  - `areaSize`
  - `areaTerrace`
  - `floorSize`
  - `landArea`
  - `level`
  - `numberOfRooms`
  - `numberOfBedrooms`
  - `numberOfBathroomsTotal`
  - `numberOfGarages`
  - `occupancy`
  - `isActive`

### Traduction `AccommodationTranslation`

- `locale`
- `slug`
- `name`
- `label`
- `highlight`
- `body`
- `review`
- `metaTitle`
- `metaDescription`

### Relations V1

- `category` vers `CategoryCode`, avec `inCodeSet = accommodation-type`.
- `realEstateListing` vers `CategoryCode`, avec `inCodeSet = real-estate-listing`.
- `place` vers `CategoryCode`, avec `inCodeSet = accommodation-place`.
- `amenityFeature` vers un tableau de `CategoryCode`, avec `inCodeSet = amenity-feature`.
- `qualities` vers un tableau de `PropertyValue`, avec les valeurs par defaut `Confort`,
  `Architecture` et `Intérieur` initialisees a `0`.
- `tags` vers un tableau de `CategoryCode`, avec `inCodeSet = tag`.
- `associatedMedia` vers `MediaObject`, avec une table de jointure portant au minimum :
  - `position`
  - `caption`
  - `keywords`
- `offer` via un objet JSON persiste simple :
  - `price`
  - `priceCurrency`
  - `priceSpecification`
  - `availability`

## Hors perimetre V1

- Import automatique complet depuis les fichiers Markdown de la webapp.
- Backoffice complet d'edition immobiliere.
- Recherche avancee, facettes, tri metier et scoring SEO.
- Workflow complet de medias reels avec upload physique depuis la source.
- Modelisation exhaustive de tous les schemas webapp lies a l'immobilier.

## Plan d'execution

- [x] Auditer les schemas source `Accommodation`, `Offer`, `RealEstateListing`,
      `AccommodationCategory`, `AccommodationPlace`, `AccommodationMedia` et `Person`.
- [x] Definir le mapping Doctrine minimal de `Accommodation` et `AccommodationTranslation`.
- [x] Choisir la strategie V1 pour `Offer` et `RealEstateAgent` sans surmodeliser.
- [x] Definir les `CategoryCode` de reference et leurs `inCodeSet` :
      `accommodation-type`, `real-estate-listing`, `accommodation-place`,
      `amenity-feature` et `tag`.
- [x] Ajouter les migrations Doctrine.
- [x] Brancher `Accommodation` sur les providers/processors project-scoped existants.
- [x] Ajouter les groupes de serialisation et le contrat API de lecture minimal.
- [x] Adapter `LoadDemoFixturesCommand` pour creer les `CategoryCode`, medias et 5
      accommodations relationnelles.
- [x] Retirer le chargement des biens immobiliers sous forme de `CreativeWork` si la nouvelle
      route couvre le besoin.
- [x] Mettre a jour `docs/10-operations/demo-fixtures.md`.
- [x] Ajouter ou adapter des tests fonctionnels sur la collection `accommodations`.

## Validations attendues

- [x] `docker compose exec -T api php -l src/Command/LoadDemoFixturesCommand.php`
- [x] `docker compose exec -T api php bin/console doctrine:schema:validate`
- [x] `docker compose exec -T api php bin/console lint:container`
- [x] `docker compose exec -T api php bin/console app:fixtures:load-demo`
- [x] `docker compose exec -T api php bin/phpunit`
- [x] La BDD contient 5 `Accommodation` rattaches aux bons projets.
- [x] Les biens ne sont plus seulement presents dans `creative_work_translation.schema_data`.
- [x] Les categories, lieux, tags, amenities, offres, listings et medias sont verifiables
      par relations SQL.
- [x] Les relations `CategoryCode` utilisent les bons `inCodeSet`.
- [x] Un utilisateur ne voit pas les accommodations d'un projet dont il n'est pas membre.
- [x] Les routes collection et item excluent les biens archives via `deletedAt`.

## Points de vigilance

- Securite : l'isolation projet doit rester centralisee et testee.
- Contrat API : ne pas exposer directement une structure Doctrine trop interne si un DTO de
  lecture devient necessaire.
- Donnees : eviter un modele JSON fourre-tout pour les champs metier filtrables.
- SEO : conserver `slug`, `metaTitle`, `metaDescription`, `highlight` et `body` comme champs
  localises.
- Performance : indexer `project_id`, `status`, `is_active`, `deleted_at`, `slug` et les
  relations utiles aux collections.
- KISS : garder `Offer`, `RealEstateListing` et `Agent` simples tant que le besoin backoffice
  n'est pas stabilise.

## Addendum 2026-05-23 : contrat de lecture Accommodation

- [x] La lecture `Accommodation` n'expose plus la collection technique `translations`.
- [x] Les champs localises sont servis directement au niveau racine selon la locale demandee
      (`?locale=...` ou `Accept-Language`) avec fallback vers la locale source du projet.
- [x] Les relations directes de l'`Accommodation` sont exposees par UUID au lieu d'embarquer
      l'objet complet : `category`, `realEstateListing`, `place`, `amenityFeature`, `tags`,
      `project`, `createdBy`, `updatedBy`.
- [x] Les medias associes gardent leurs metadonnees de jointure (`position`, `caption`,
      `keywords`) mais referencent `mediaObject` par UUID.
- [x] Le projet par defaut reste en source locale `fr` et active `fr`, `en`, `es`.
- [x] Le contrat est couvert par `ProjectIsolationTest::testAccommodationApiResponseUsesUuidRelationsAndResolvedLocaleFields`.

## Addendum 2026-05-24 : contrat de lecture API generalise

- [x] Le compactage de lecture n'est plus limite a `Accommodation` : les ressources API
      exposent leurs relations directes par UUID.
- [x] Les collections techniques `translations` sont masquees sur les lectures standard,
      tandis que les champs localises resolus restent exposes au niveau racine.
- [x] Les objets de jointure ou de valeur conservent leurs champs utiles, mais leurs
      relations imbriquees sont aussi compactees en UUID.
- [x] Le contrat est couvert par
      `ProjectIsolationTest::testGenericApiResponsesUseUuidRelationsAndHideTranslationCollections`
      et `ProjectIsolationTest::testProjectApiResponseUsesUuidRelations`.
