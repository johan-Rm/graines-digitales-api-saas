# Task : Composants `hasPart` des Accommodations

## Metadonnees

- ID : TASK-027
- Source : besoin produit — sections / écrans de la fiche accommodation — 2026-06-07
- Statut : À faire
- Périmètre : `Accommodation`, nouvelles entités `AccommodationComponent` /
  `AccommodationComponentTranslation`, enums, résolution de locale, schemas (`webapp` +
  `dtos/symfony_api`), migration, tests
- Dépendances fonctionnelles : TASK-002 (translation backend), TASK-014 (auto-translation),
  TASK-019 (slug accommodation)

## Objectif

La ressource `Accommodation` doit exposer `hasPart` : une liste ordonnée de blocs de
contenu (« écrans ») composant la fiche d'un bien. `hasPart` (concept Schema.org,
« ex-components / sections ») n'existe aujourd'hui ni dans l'entité `Accommodation`, ni
dans `schemas/webapp/accommodation.yaml`, ni dans `schemas/dtos/symfony_api/accommodation.yaml`.
Il n'est défini que côté `WebPage` dans `schemas/`, sans implémentation API.

Chaque bloc mélange du **structurel partagé entre langues** (type d'écran, médias, options
d'affichage, ordre) et du **texte localisé** (`headline`, `text`, `name`). La structure ne
doit pas être dupliquée par locale ; seuls les textes sont traduits.

## Décisions de cadrage

1. Contenu localisé : un bloc porte du texte éditorial traduit (`headline`, `text`, `name`).
2. Structure partagée entre locales (type, médias, meta, ordre) — pas de duplication.
3. Deux entités, pattern translatable Sylius-style du projet :
   `AccommodationComponent` (racine structurelle) + `AccommodationComponentTranslation`.
4. Embarqué dans `Accommodation` (cascade `persist`/`remove`, `orphanRemoval`), comme
   `associatedMedia` et `qualities`. Aucun nouvel endpoint.
5. `name` localisé (côté translation) par défaut — à basculer racine si désignation interne.
6. Propriété exposée sous le nom `hasPart` (conforme Schema.org).

## Structure d'un bloc (contrat)

```yaml
hasPart:
  - additionalType: SCREEN_ACCOMMODATION_TRYPTIQUE   # enum fermé (7 valeurs), structurel
    name: ''            # désignation — localisé
    headline: ''        # titre — localisé
    text: ''            # paragraphe — localisé
    associatedMedia: [] # UUID médias ordonnés — structurel (référence dénormalisée)
    meta:
      reverse: false        # structurel
      overlayMode: dark     # dark | light — structurel
```

## Périmètre

### Enums (`src/Enum/`)

- `AccommodationComponentType: string` — 7 cas :
  `SCREEN_ACCOMMODATION_TRYPTIQUE`, `_OVERLAY`, `_FULL`, `_SPLIT`, `_CAROUSEL`, `_DUO`, `_CONTACT`.

### Entité `AccommodationComponent` (racine, partagée entre locales)

- `use UuidIdentity` ; `implements TranslatableResource`, `use TranslatableResourceBehavior`.
- `ManyToOne Accommodation` (`inversedBy: 'hasPart'`, `JoinColumn onDelete: CASCADE`).
- `additionalType` : `#[ORM\Column(enumType: AccommodationComponentType::class)]` (requis).
- `position` : `int` (ordre, `OrderBy ASC`).
- `associatedMedia` : `#[ORM\Column(type: 'json')]` `list<string>` d'UUID média ordonnés
  (référence dénormalisée, calque de `keywords` sur `AccommodationMedia`).
- `meta` : `#[ORM\Column(type: 'json')]` objet JSON **libre** (`array<string, mixed>`, forme non
  contrainte) stocké tel quel. Ex. d'usage : `reverse` (bool), `overlayMode` (string).
- `translations` : `OneToMany AccommodationComponentTranslation` (cascade `persist`/`remove`, `orphanRemoval`).
- Getters/setters localisés délégués à la translation courante : `getHeadline/setHeadline`,
  `getText/setText`, `getName/setName` (calque de `Accommodation::getName`).
- `createTranslation()`, `addTranslation()`, `getTranslations()`.

### Entité `AccommodationComponentTranslation` (textes localisés)

- `use UuidIdentity`, `use ResourceTranslationFields` ; `implements ResourceTranslation`.
- `ManyToOne AccommodationComponent` (`translatable`, `JoinColumn onDelete: CASCADE`).
- `name` (string court), `headline` (string), `text` (`type: 'text'`).
- `UniqueConstraint (component_id, locale)`.
- `touch(?User $actor = null)`.

### Modification de `Accommodation`

- Collection `private Collection $hasPart` : `OneToMany AccommodationComponent mappedBy 'accommodation'`,
  cascade `persist`/`remove`, `orphanRemoval`, `OrderBy ['position' => 'ASC']`, groupes `['read','write']`.
- Accesseurs `getHasPart()/addComponent()/removeComponent()/clearHasPart()` (idioms de `associatedMedia`).
- Initialisation dans `__construct`.

### Résolution de locale imbriquée (point sensible)

La couche qui résout la locale de l'`Accommodation` (provider / normalizer appelant
`setResolvedLocale`) doit **propager** `requestedLocale` / `currentLocale` / `fallbackLocale`
à chaque `AccommodationComponent`, avec la même logique de fallback vers la locale source.
Sans cette propagation, `getHeadline()` / `getText()` / `getName()` des composants ne se
résolvent pas. Les composants n'exposent pas de métadonnées `fallback` propres (KISS) : seul
le niveau `Accommodation` expose `locale` / `fallbackLocale` / `fallback`.

### API et sérialisation

- Aucun nouvel endpoint : `hasPart` lu/écrit via le payload `Accommodation` existant.
- En POST/PUT dans une locale donnée, `headline`/`text`/`name` d'un composant ciblent la
  translation de cette locale (délégation `currentLocale`).
- Sérialisation par composant : `additionalType`, `position`, `associatedMedia`, `meta`,
  + `headline`/`text`/`name` résolus.
- Le pattern State central (`ProjectScoped*` + `ProjectAccessGuard`) reste inchangé : l'accès
  passe par l'`Accommodation` parente.

### Schémas (contrats partagés)

- `schemas/webapp/accommodation.yaml` : ajout `hasPart: type: array<object>` documenté.
- `schemas/dtos/symfony_api/accommodation.yaml` : ajout `hasPart` avec shape nested
  (`additionalType`, `name`, `headline`, `text`, `associatedMedia: array<uuid>`,
  `meta` : objet JSON libre).

### Migration

- Tables `accommodation_component` (index `accommodation_id, position`) et
  `accommodation_component_translation` (unique `component_id, locale`).
- Réversible, compatible déploiement sans downtime (ajout de tables uniquement).

## Tradeoffs assumés

- `associatedMedia` en JSON d'UUID ⇒ pas d'intégrité FK ni cascade au delete d'un
  `MediaObject` (choisi pour rester à 2 entités, cohérent avec le caractère « bloc de
  contenu » ; calque de `keywords`). Risque de références orphelines à filtrer côté front.
- Structure partagée ⇒ ordre et structure communs à toutes les langues (objectif visé).

## Validation

- `additionalType` requis et conforme à l'enum ; `meta` accepté tel quel (objet JSON libre).
- Translations alignées sur la validation existante des translations d'accommodation
  (locale autorisée, présence locale source selon le pattern projet).
- **Unit** : délégation locale des getters/setters du composant ; normalisation `setMeta` ;
  mapping des enums.
- **Fonctionnel** : création d'une accommodation avec `hasPart` ; lecture en locale source
  puis en locale avec fallback (textes composants résolus) ; mise à jour + réordonnancement ;
  suppression (orphanRemoval) ; isolation inter-projets préservée (invariant de sécurité).
- `docker compose exec api php bin/console doctrine:schema:validate` OK.
- DTO `schemas/dtos/symfony_api/accommodation.yaml` cohérent avec les groupes de
  sérialisation réellement exposés.

## Hors périmètre

- Endpoints dédiés `…/accommodations/{id}/components` et routes de traduction granulaires.
- Entité de jointure média dédiée (`AccommodationComponentMedia`) avec FK.
- Implémentation de `hasPart` côté `WebPage` (contrat existant, sans implémentation) — sujet distinct.
