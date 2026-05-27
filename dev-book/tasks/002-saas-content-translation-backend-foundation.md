# Task : Socle de traduction SaaS des contenus

## Metadonnees

- ID : TASK-002
- Source : ajustement utilisateur du 2026-05-19
- Statut : En cours
- Perimetre : API Symfony 8 / API Platform pour contenus multilingues
- Dossier cible : `api/symfony-8-api-platform/`
- Inspiration : `/home/johan/www/kazen-garden/digital-management-system`
- Dependance fonctionnelle : TASK-001

## Objectif

Mettre en place un systeme de traduction des contenus "facon Sylius" pour permettre a une
meme ressource metier de porter plusieurs traductions localisees, sans dupliquer les donnees
de tenancy, d'audit, de publication ou de securite.

Le systeme doit rester compatible avec le SaaS multi-organisations et multi-projets :
toute traduction appartient indirectement au `project` de sa ressource racine et ne doit
jamais contourner les regles d'isolation projet.

## Reference d'inspiration

Le projet Kazen utilise le pattern suivant :

- une entite racine translatable, par exemple `WebPage`, `Article`, `Category`
- une entite de traduction associee, par exemple `WebPageTranslation`, `ArticleTranslation`,
  `CategoryTranslation`
- les champs communs restent sur l'entite racine
- les champs editoriaux localises sont delegates a la traduction courante
- chaque traduction porte une locale
- le getter de l'entite racine expose souvent la valeur de `getTranslation()`

Ce pattern est pertinent pour le projet, mais il doit etre adapte a Symfony 8, API Platform 4,
Doctrine ORM 3, aux DTOs explicites et a l'isolation stricte par projet.

## Decision d'architecture attendue

- Reprendre le meme modele de conception que Sylius `Translatable` / `Translation`.
- Une ressource racine expose une collection de traductions et une traduction courante.
- Une traduction connait sa ressource racine, sa locale et ses champs localises.
- Si les composants Sylius sont compatibles Symfony 8 / Doctrine ORM 3 et apportent une vraie
  valeur, ils peuvent etre utilises.
- Si la dependance Sylius ajoute trop de surface, reproduire localement le pattern en gardant
  les noms, responsabilites et invariants du modele Sylius.
- Les entites racines portent l'identite, le projet, les statuts, l'audit et les relations
  non localisees.
- Les entites `*Translation` portent les champs localises, la locale, le slug localise si
  necessaire, les metas SEO localisees et les donnees Schema.org textuelles localisees.

## Perimetre MVP

### Ressources a rendre traduisibles en priorite

- `WebPage`
- `Article`
- `CategoryCode`

### Champs localisables prioritaires

- `slug`
- `headline`
- `body`
- `metaTitle`
- `metaDescription`
- `label`
- `name`
- `text`
- champs Schema.org textuels presents dans `schemaData`

### Locales MVP

- locale source projet : par defaut `fr`
- locales cibles configurables par projet
- support initial attendu : `fr`, `en`, `es`, `ar`

## Hors perimetre MVP

- Traduction automatique IA ou Google Translate en production.
- Workflow complet de validation humaine des traductions.
- Interface backoffice de traduction.
- Detection automatique de langue.
- Traduction automatique des medias, images ou fichiers.
- Traduction exhaustive de tous les futurs modeles metier long terme.

## Contraintes d'architecture

- Une traduction ne doit pas porter directement un `project_id` si la relation racine suffit,
  sauf justification de performance ou de securite explicite.
- Toute requete sur une traduction doit verifier l'acces au projet de la ressource racine.
- Une ressource racine ne doit pas etre dupliquee par langue.
- Une paire `(resource_id, locale)` doit etre unique.
- Les slugs doivent etre uniques par projet, ressource et locale selon la strategie retenue.
- Les collections API standards doivent pouvoir retourner une representation localisee selon
  une locale demandee.
- Le fallback ne doit jamais masquer une absence de traduction dans les workflows d'edition.
- Les DTOs doivent rendre explicite la difference entre payload localise et payload complet
  de gestion des traductions.

## Strategie API

Prevoir deux usages distincts :

1. Lecture publique ou cliente localisee :
   - recuperer une ressource dans une locale donnee
   - appliquer un fallback controle si autorise
   - exposer uniquement la traduction courante dans le DTO de lecture

2. Gestion backoffice des traductions :
   - lister toutes les traductions d'une ressource
   - creer ou mettre a jour une traduction par locale
   - connaitre l'etat de complétude par locale

Routes cibles a specifier :

- `GET /api/projects/{projectId}/web-pages?locale=fr`
- `GET /api/projects/{projectId}/web-pages/{id}?locale=fr`
- `GET /api/projects/{projectId}/web-pages/{id}/translations`
- `PUT /api/projects/{projectId}/web-pages/{id}/translations/{locale}`

Les routes exactes peuvent varier selon les contraintes API Platform, mais le contrat doit
rester lisible et stable.

## Strategie de fallback

- Definir une locale source par projet.
- Definir les locales activees par projet.
- Si `locale` est absente, utiliser la locale par defaut du projet.
- Si la traduction demandee est absente :
  - lecture publique : fallback possible vers la locale source, avec indicateur explicite
  - backoffice : ne pas masquer l'absence, retourner l'etat manquant
- Le DTO de sortie doit pouvoir indiquer :
  - `locale`
  - `requestedLocale`
  - `fallbackLocale`
  - `isFallback`

## Strategie de donnees

- Ajouter une structure commune pour les traductions :
  - `id`
  - `locale`
  - `createdAt`
  - `updatedAt`
  - `createdBy`
  - `updatedBy`
- Ajouter les contraintes SQL d'unicite par ressource et locale.
- Indexer les locales et slugs utiles aux lectures clientes.
- Prevoir les migrations Doctrine associees.
- Migrer les champs actuellement localises depuis les entites racines vers les tables de
  traduction seulement quand la strategie est stabilisee.

## Strategie DTO et schemas

- Mettre a jour `schemas/dtos/symfony_api/` pour les ressources traduisibles exposees.
- Documenter les ecarts avec `schemas/webapp/`.
- Distinguer :
  - DTO de lecture localisee
  - DTO de gestion des traductions
  - DTO de statut de complétude par locale

## Strategie de tests

Prioriser les tests suivants :

- creation d'une ressource avec traduction source
- ajout d'une traduction cible
- unicite `(resource, locale)`
- lecture avec locale explicite
- lecture sans locale avec locale projet par defaut
- fallback public controle
- absence de fallback en contexte backoffice
- isolation projet sur les traductions
- refus d'acces a une traduction si l'utilisateur n'est pas membre du projet racine
- slug localise unique dans un projet

## Plan d'execution

- [x] Auditer le pattern Kazen `Translatable` / `Translation` et en extraire les invariants utiles.
- [x] Verifier si une dependance Sylius compatible Symfony 8 est pertinente ou si une implementation locale est preferable.
- [x] Definir une interface ou un trait local `TranslatableResource` si utile.
- [x] Definir une interface ou un trait local `ResourceTranslation` si utile.
- [x] Ajouter la configuration de locales par projet.
- [x] Ajouter la locale source par projet.
- [x] Creer les entites de traduction MVP : `WebPageTranslation`, `ArticleTranslation`, `CategoryCodeTranslation`.
- [x] Deplacer les champs localises prioritaires vers les entites de traduction.
- [x] Ajouter les contraintes SQL et index de locale/slug.
- [x] Adapter les providers/processors API Platform pour resoudre la traduction courante.
- [x] Ajouter un resolver de locale SSR/API-safe base sur query param, header ou defaut projet.
- [x] Ajouter une strategie de fallback explicite.
- [ ] Definir les DTOs de lecture localisee.
- [ ] Definir les DTOs de gestion des traductions.
- [ ] Mettre a jour les schemas DTO Symfony API.
- [x] Ajouter les migrations Doctrine.
- [ ] Ajouter les tests fonctionnels de lecture localisee.
- [ ] Ajouter les tests de securite multi-projet sur les traductions.
- [x] Documenter le modele de traduction dans le README ou une note technique.

## Validations attendues

- [x] Les migrations s'executent sur PostgreSQL depuis un environnement propre.
- [x] Une ressource peut avoir plusieurs traductions.
- [x] Une ressource ne peut pas avoir deux traductions pour la meme locale.
- [x] La lecture localisee retourne la bonne traduction.
- [x] Le fallback est explicite et testable.
- [ ] Les routes backoffice ne masquent pas les traductions manquantes.
- [ ] Les traductions respectent strictement l'isolation projet.
- [ ] Les DTOs exposent clairement la locale demandee et la locale effectivement servie.
- [ ] Les tests PHPUnit couvrent les regles critiques.
