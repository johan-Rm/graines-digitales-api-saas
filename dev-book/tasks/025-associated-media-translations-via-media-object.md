# Task : Traductions des textes alt des medias associes aux accommodations

## Metadonnees

- ID : TASK-025
- Source : analyse dashboard Accommodation du 2026-06-03
- Statut : A faire
- Perimetre : API Symfony, dashboard Accommodation, MediaObject, MediaObjectTranslation
- Dependances fonctionnelles : TASK-012, TASK-014, TASK-023

## Objectif

Permettre au dashboard de modifier les textes alt des images associees a un bien
immobilier depuis le payload de sauvegarde de l'`Accommodation`, sans introduire
`AccommodationMediaTranslation`.

Le backend doit conserver `MediaObjectTranslation` comme source de verite des textes
localises du media. Les traductions recues dans `associatedMedia[].translations` sont donc
des commandes imbriquees appliquees au `MediaObject` reference par l'association.

## Decision de modele

Pour cette V1, ne pas creer de table de traduction specifique a l'association
`AccommodationMedia`.

Responsabilites retenues :

- `AccommodationMedia` porte les informations non localisees de l'association :
  - `mediaObject`
  - `position`
  - `keywords`
  - `representativeOfPage`
- `MediaObjectTranslation` porte les textes localises du media :
  - `caption`, utilise par le dashboard comme texte alt

Le champ `AccommodationMedia.caption` existant est considere comme legacy/fallback. Il ne
doit pas devenir une seconde source de verite pour le texte alt multilingue.

## Contrat payload cible

Le dashboard peut envoyer des traductions media dans chaque item `associatedMedia` :

```json
{
  "associatedMedia": [
    {
      "mediaObject": "019e8d18-370e-7e7a-81f8-2def884aca8e",
      "position": 1,
      "caption": "Espace interieur renove avec finitions modernes",
      "keywords": ["gallery"],
      "representativeOfPage": true,
      "translations": [
        {
          "locale": "fr",
          "caption": "Patio lumineux de riad"
        },
        {
          "locale": "en",
          "caption": "Bright riad patio"
        }
      ]
    }
  ]
}
```

Interpretation backend :

- `mediaObject`, `position`, `keywords`, `representativeOfPage` sont appliques a
  `AccommodationMedia`.
- `translations[]` est applique a `MediaObjectTranslation` du `MediaObject` reference.
- `caption` a la racine de l'item reste supporte comme compatibilite legacy, mais ne doit
  pas etre utilise pour representer les traductions multilingues.

## Contrat de lecture cible

En lecture d'une `Accommodation`, l'API peut exposer un champ resolu sur chaque media
associe pour simplifier le dashboard :

```json
{
  "associatedMedia": [
    {
      "mediaObject": "019e8d18-370e-7e7a-81f8-2def884aca8e",
      "position": 1,
      "caption": "Patio lumineux de riad",
      "keywords": ["gallery"],
      "representativeOfPage": true
    }
  ]
}
```

La valeur de `caption` exposee doit venir en priorite de `MediaObjectTranslation.caption`
dans la locale resolue. Le fallback eventuel doit suivre la strategie de locale du projet,
puis seulement utiliser `AccommodationMedia.caption` comme compatibilite legacy si
necessaire.

## Strategie d'implementation recommandee

Ne pas utiliser d'event asynchrone pour cette V1. La sauvegarde des traductions media fait
partie de la commande de sauvegarde du bien et doit rester transactionnelle.

Extraire un service dedie, par exemple :

```txt
AccommodationAssociatedMediaPayloadApplier
```

Responsabilites du service :

- valider que `associatedMedia` est une liste d'objets
- resoudre chaque `mediaObject` par UUID ou IRI
- verifier que le `MediaObject` appartient au `Project` courant
- reconstruire les `AccommodationMedia` avec `position`, `keywords` et
  `representativeOfPage`
- appliquer `associatedMedia[].translations[]` sur les `MediaObjectTranslation`
- persister les traductions media dans la meme transaction que l'`Accommodation`

`AccommodationStructuralPayloadApplier` pourra deleguer la partie `associatedMedia` a ce
service pour eviter d'accumuler trop de responsabilites.

## Regles fonctionnelles

- Une locale vide ou absente dans `associatedMedia[].translations[]` doit etre ignoree ou
  refusee explicitement selon la convention retenue pour les autres routes de traduction.
- Une traduction media non vide fournie par le dashboard est personnalisee et ne doit pas
  etre ecrasee par l'auto-traduction.
- Les traductions media peuvent etre completees par le workflow existant
  `ResourceAutoTranslator` si la source locale contient une valeur exploitable.
- Un `MediaObject` hors projet courant doit etre refuse ou ignore avec une erreur claire ;
  il ne doit jamais etre associe ni traduit.
- La sauvegarde doit etre atomique : en cas d'erreur sur une traduction media, la
  sauvegarde de l'`Accommodation` ne doit pas produire un etat partiel.

## Limite assumee

Les textes alt sont globaux au `MediaObject` dans le projet. Si le meme media est reutilise
sur plusieurs biens, modifier son texte alt depuis un bien modifie le texte affiche pour
ce media partout ou il est reutilise.

Si un texte alt different par bien devient necessaire, il faudra introduire
`AccommodationMediaTranslation`.

## Plan d'execution

- [ ] Documenter le contrat dans `docs/03-api-contracts/dashboard-accommodation-save.md`.
- [ ] Extraire la gestion `associatedMedia` dans un service dedie.
- [ ] Appliquer `associatedMedia[].translations[]` sur `MediaObjectTranslation`.
- [ ] Brancher l'auto-traduction media sans ecraser les valeurs personnalisees.
- [ ] Adapter la normalisation de `associatedMedia.caption` pour exposer la caption
      resolue depuis `MediaObjectTranslation`.
- [ ] Ajouter des tests fonctionnels de sauvegarde accommodation avec traductions media
      imbriquees.
- [ ] Ajouter un test d'isolation projet sur un `mediaObject` hors projet.

## Validation cible

```bash
docker compose exec -T api php bin/console lint:container
docker compose exec -T api php bin/phpunit tests/Functional/AccommodationCrudTest.php
docker compose exec -T api php bin/phpunit tests/Functional/MediaObjectCrudTest.php
docker compose exec -T api php bin/phpunit tests/Functional/ProjectIsolationTest.php
```

## Points de vigilance

- Securite : ne jamais modifier une `MediaObjectTranslation` d'un autre projet.
- Contrat : clarifier que `associatedMedia[].translations` cible le `MediaObject`, pas
  l'association `AccommodationMedia`.
- Donnees : eviter la coexistence durable de deux sources de verite entre
  `AccommodationMedia.caption` et `MediaObjectTranslation.caption`.
- SEO/accessibilite : le champ edite par le dashboard comme texte alt doit rester
  localise et stable dans les reponses publiques.
