# Task : CRUD dashboard des CategoryCode

## Metadonnees

- ID : TASK-013
- Source : brief backend dashboard du 2026-05-22
- Statut : Terminee
- Cloture : 2026-05-22
- Perimetre : API Symfony, API Platform, CategoryCode, Accommodation
- Dependances fonctionnelles : TASK-008, TASK-012

## Objectif

Permettre au dashboard de creer et supprimer des `CategoryCode` projet, tout en
conservant un contrat strict : un code est immuable apres creation et une suppression est
refusee si le code est reference par au moins un bien du meme projet.

## Contrat MVP

- Conserver la creation via :
  - `POST /api/projects/{projectId}/category-codes`
  - `Content-Type: application/ld+json`
  - `Accept: application/ld+json`
- Accepter un payload minimal :

```json
{ "code": "local-commercial", "inCodeSet": "accommodation-type" }
```

- Retourner `201` avec au minimum `@id`, `code` et `inCodeSet`.
- Refuser la creation en `409` quand le couple `(code, inCodeSet)` existe deja dans le
  meme projet.
- Refuser la creation en `422` quand `code` ou `inCodeSet` est manquant ou invalide.
- Valider `code` en kebab-case.
- Garder `inCodeSet` comme valeur libre, sans enum imposee.
- Supprimer uniquement via :
  - `DELETE /api/projects/{projectId}/category-codes/{uuid}`
- Retourner `204` quand la suppression est effectuee.
- Retourner `404` quand l'UUID est inconnu ou n'appartient pas au projet demande.
- Retourner `409` quand le code est utilise par au moins un `Accommodation` du meme
  projet.
- Ne pas exposer de `PUT` ou `PATCH` pour `CategoryCode` : changer un code revient a
  creer le nouveau code puis supprimer l'ancien s'il est libre.

## Plan d'execution

- [x] Verifier le contrat actuel de `CategoryCode` dans API Platform, notamment la
  presence de `Put` et le processor generique de suppression.
- [x] Retirer ou neutraliser les operations de modification `PUT` / `PATCH` pour
  garantir l'immutabilite du couple `code` / `inCodeSet`.
- [x] Renforcer la validation de creation :
  - `code` obligatoire, kebab-case, longueur compatible avec la colonne existante.
  - `inCodeSet` obligatoire, valeur libre, longueur compatible avec la colonne existante.
- [x] Mapper proprement le doublon `(projectId, inCodeSet, code)` en reponse `409`, sans
  masquer les autres erreurs Doctrine.
- [x] Implementer un guard de suppression dedie aux `CategoryCode`, sans modifier le
  processor generique des autres ressources projet si ce n'est pas necessaire.
- [x] Compter les `Accommodation` du meme projet qui referencent le code via :
  - `category`
  - `realEstateListing`
  - `place`
  - `amenityFeature`
  - `tags`
- [x] Retourner une erreur `409` explicite quand au moins un bien reference le code :

```json
{
  "type": "https://tools.ietf.org/html/rfc2616#section-10",
  "title": "Cannot delete CategoryCode",
  "status": 409,
  "detail": "Ce code est utilise par 3 bien(s) et ne peut pas etre supprime."
}
```

- [x] Ajouter ou adapter les tests fonctionnels API pour couvrir :
  - creation valide et presence de `@id`.
  - creation refusee si doublon dans le meme projet.
  - creation autorisee si meme couple dans un autre projet.
  - creation refusee si `code` n'est pas en kebab-case.
  - absence de modification possible.
  - suppression valide d'un code libre.
  - suppression refusee en `409` avec nombre de biens concernes.
  - suppression en `404` pour un UUID hors projet.
- [x] Mettre a jour la documentation API utile si le contrat public change par rapport a
  l'etat actuel.

## Validations

```bash
docker compose exec -T api php bin/console lint:container
docker compose exec -T api php bin/console doctrine:schema:validate
docker compose exec -T api php bin/phpunit tests/Functional
docker compose exec -T api php bin/phpunit
```

## Points de vigilance

- Securite : respecter `ProjectAccessGuard`; une ressource hors projet doit rester
  masquee en `404`.
- Contrat API : la reponse de creation doit contenir `@id` pour permettre au dashboard
  Nuxt de mettre a jour son cache IRI immediatement.
- Integrite : ne pas s'appuyer uniquement sur les contraintes SQL, car le dashboard a
  besoin d'un `409` metier lisible avec le nombre de biens concernes.
- Maintenabilite : eviter d'etendre le processor generique si la regle ne concerne que
  `CategoryCode`.
