# Strategie de tests

Tests critiques couverts par la suite fonctionnelle API Platform :

- refus sans JWT
- login JWT valide
- refresh token valide
- refresh token invalide refuse
- utilisateur membre autorise
- utilisateur non membre refuse
- isolation inter-projets
- lecture localisee
- fallback de traduction explicite
- upload image
- rejet des fichiers non image
- soft delete
- coherence des fixtures d'hebergements et de leurs relations metier

Les tests ne doivent pas dependre de donnees inserees manuellement.

## Execution locale

```bash
docker compose exec api php bin/phpunit
```

Validation backend complete avant merge :

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

## Limites connues

Les tests fonctionnels recreent le schema et chargent les fixtures de demo avant chaque
test pour rester reproductibles. Cette strategie est simple et fiable pour le MVP, mais
elle rend la suite plus lente qu'une strategie transactionnelle ou qu'un snapshot de base.

La creation d'une traduction manquante via `PUT` n'est pas couverte ici : le test de
lecture localisee cree explicitement une traduction en base pour verifier le contrat de
lecture et de fallback sans elargir le scope de TASK-005.
