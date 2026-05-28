# Task : Generalisation du workflow de traduction automatique

## Metadonnees

- ID : TASK-014
- Source : extension du besoin dashboard Accommodation valide le 2026-05-24
- Statut : Terminé
- Perimetre : API Symfony, ressources traduisibles, routes backoffice de traduction, Google Translate
- Dependances fonctionnelles : TASK-002, TASK-012

## Objectif

Generaliser le comportement valide sur la sauvegarde dashboard `Accommodation` a toutes
les ressources qui portent des traductions localisees.

Le backend doit permettre de sauvegarder une traduction source et de completer
automatiquement les champs manquants des autres locales activees du projet, sans jamais
ecraser une valeur personnalisee par l'utilisateur.

## Ressources concernees

- `Accommodation`
- `WebPage`
- `Article`
- `CategoryCode`

## Routes concernees

Routes generiques de gestion des traductions :

```txt
PUT /api/projects/{projectId}/web-pages/{id}/translations/{locale}
PUT /api/projects/{projectId}/articles/{id}/translations/{locale}
PUT /api/projects/{projectId}/category-codes/{id}/translations/{locale}
PUT /api/projects/{projectId}/accommodations/{id}/translations/{locale}
```

Route dashboard specifique Accommodation deja validee :

```txt
PUT /api/projects/{projectId}/accommodations/{identifier}?locale={locale}
```

## Regles de traduction

- L'`identifier` ou l'`id` de la ressource racine reste commun a toutes les locales.
- La locale source du projet reste la source prioritaire de traduction automatique.
- Si seule la traduction source est fournie, les traductions manquantes des autres
  locales activees du projet peuvent etre creees automatiquement.
- Si plusieurs traductions sont fournies, le process automatique ne complete que les
  champs absents, `null` ou vides.
- Une valeur non vide est consideree comme personnalisee par l'utilisateur et ne doit
  jamais etre ecrasee par la traduction automatique.
- Une locale non activee sur le projet doit etre refusee.
- Les champs metier globaux restent portes par la ressource racine et ne doivent pas etre
  dupliques dans chaque traduction.

## Google Translate

- Le service cible est Google Translate.
- Tant que la configuration definitive n'est pas disponible, le client peut fonctionner
  en mode fictif et retourner la chaine source.
- Le branchement reel devra isoler les credentials, les erreurs reseau, les quotas et les
  timeouts derriere une interface locale.

## Contrat technique attendu

- Extraire la logique `AccommodationAutoTranslator` vers un service generique reutilisable
  par toutes les ressources `TranslatableResource`.
- Eviter la duplication des listes de champs localises quand une entite de traduction
  expose les memes champs editoriaux.
- Conserver les routes `/translations/{locale}` existantes et leur comportement de
  sauvegarde par locale.
- Ajouter, si necessaire, un payload multilingue explicite sur les routes item qui en ont
  besoin, sans transformer les routes item en routes bulk.
- Preserver les garanties d'isolation projet et de membership.

## Note dashboard frontend

- Continuer a envoyer une valeur non vide uniquement quand l'utilisateur souhaite une
  traduction personnalisee.
- Envoyer un champ absent, `null` ou vide quand le dashboard souhaite laisser l'API
  completer automatiquement la traduction.
- Utiliser uniquement les locales exposees par `enabledLocales` du projet.
- Sur les ecrans multilingues, afficher clairement quelles locales sont personnalisees et
  quelles locales ont ete auto-completees.

## Plan d'execution

- [x] Inventorier les champs localises reels par type de traduction.
- [x] Extraire un service generique de completion automatique (`ResourceAutoTranslator`).
- [x] Brancher le service sur les routes `/translations` (PUT et POST bulk).
- [x] Retirer la traduction automatique du controller dashboard Accommodation.
- [ ] Ajouter des tests fonctionnels par famille de ressource.
- [x] Documenter le contrat commun dans `docs/TRANSLATIONS.md`.
- [x] Valider `lint:container`.

## Validation attendue

```bash
docker compose exec -T api php bin/console lint:container
docker compose exec -T api php bin/phpunit tests/Functional/TranslationTest.php
docker compose exec -T api php bin/phpunit tests/Functional/DashboardAccommodationSaveTest.php
```

## Points de vigilance

- Securite : ne jamais traduire ou exposer une ressource hors projet courant.
- Contrat : ne pas casser les payloads existants des routes `/translations/{locale}`.
- Donnees : ne pas ecraser les valeurs personnalisees non vides.
- Performance : eviter les appels Google Translate synchrones non bornes sur de gros
  payloads ; prevoir une limite ou un traitement asynchrone si le volume augmente.
