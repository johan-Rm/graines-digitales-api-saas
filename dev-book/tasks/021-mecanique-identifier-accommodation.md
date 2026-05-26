# Task : Identifiant mécanique des Accommodations

## Metadonnees

- ID : TASK-021
- Source : règle métier 3.1 — identifiants des fiches accommodation
- Statut : Fait
- Périmètre : `Accommodation`, `ProjectScopedPersistProcessor`, génération serveur
- Dépendances fonctionnelles : TASK-008 (Accommodation), TASK-012 (dashboard save), TASK-019 (slug mécanique)

## Objectif

Le champ `identifier` d'une fiche accommodation est généré côté backend depuis le couple
exact `realEstateListing + category`.

Format :

```text
INITIALS(realEstateListing.code) + INITIALS(category.code) + compteur sur 3 chiffres
```

Les initiales sont calculées depuis les mots séparés par `-`, en majuscules.

Exemples :

- `bien-a-vendre` + `appartement` -> `BAVA001`
- `bien-a-vendre` + `maison-de-campagne` -> `BAVMDC001`
- `location-longue-duree` + `appartement` -> `LLDA001`
- `location-saisonniere` + `villa-golf` -> `LSVG001`

Le compteur est incrémental par projet et par couple exact `realEstateListing + category`.
Il repart à `001` pour chaque nouveau couple.

## Changements réalisés

- `identifier` est retiré du groupe `write` de l'API : il est exposé en lecture seule.
- `ProjectScopedPersistProcessor` génère l'identifiant quand une nouvelle accommodation
  arrive sans identifier.
- Le contrôleur dashboard par identifier n'accepte plus d'écraser l'identifier depuis le
  payload.
- La génération tient compte des biens non archivés existants du même projet et du même
  couple.

## Points de vigilance

- L'identifiant reste stable après création. Changer `realEstateListing` ou `category`
  sur un bien existant ne régénère pas sa référence, afin de ne pas casser les URLs et les
  sauvegardes dashboard par identifier.
- La contrainte unique `(project_id, identifier)` reste la protection finale contre une
  collision concurrente.
