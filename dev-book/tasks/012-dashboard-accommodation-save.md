# Task : Sauvegarde dashboard des accommodations par identifiant

## Metadonnees

- ID : TASK-012
- Source : besoin dashboard Nuxt du 2026-05-21
- Statut : Terminee
- Cloture : 2026-05-22
- Perimetre : API Symfony, dashboard Nuxt, Accommodation, CategoryCode
- Dependances fonctionnelles : TASK-001, TASK-002, TASK-008

## Objectif

Permettre au dashboard Nuxt de sauvegarder un bien immobilier identifie par son
`identifier` metier, par exemple `BAVLC001`, sans connaitre a l'avance l'UUID Doctrine de
l'`Accommodation`.

## Contrat MVP

- Seeder le `CategoryCode` manquant `local-commercial` dans `accommodation-type` et
  completer les jeux de codes utilises par le dashboard immobilier.
- Confirmer que les endpoints projet existent :
  - `GET /api/projects/{projectId}/category-codes`
  - `GET /api/projects/{projectId}/accommodations`
  - `PUT /api/projects/{projectId}/accommodations/{uuid}?locale=fr`
  - `POST /api/projects/{projectId}/accommodations?locale=fr`
- Ajouter une route de compatibilite dashboard :
  - `PUT /api/dashboard/accommodations/{identifier}?locale=fr`
- La route dashboard resout l'identifier sur les projets accessibles en ecriture pour
  l'utilisateur connecte, puis persiste la mise a jour en conservant les memes invariants
  metier que les routes projet.
- Si l'identifier correspond a plusieurs projets accessibles en ecriture, retourner `409`
  pour eviter une mise a jour ambigue.

## Plan d'execution

- [x] Verifier les routes API Platform existantes.
- [x] Verifier le seed `local-commercial`.
- [x] Ajouter la route dashboard par `identifier`.
- [x] Ajouter les tests fonctionnels de sauvegarde dashboard.
- [x] Valider `lint:container` et PHPUnit cible.
- [x] Valider la suite PHPUnit complete.

## Validation

```bash
docker compose exec -T api php bin/console lint:container
docker compose exec -T api php bin/console doctrine:schema:validate
docker compose exec -T api php bin/console app:fixtures:load-demo
docker compose exec -T api php bin/phpunit tests/Functional/DashboardAccommodationSaveTest.php
docker compose exec -T api php bin/phpunit
```

## Points de vigilance

- Securite : ne pas exposer un bien hors membership projet.
- Contrat : conserver les endpoints projet UUID comme source canonique.
- Donnees : le dashboard doit continuer a resoudre les `CategoryCode` en IRI avant envoi.
