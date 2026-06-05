# Validation CI

Pendant les interventions IA et les petites corrections locales, lancer seulement les
validations ciblees utiles au changement. La suite PHPUnit complete ne doit pas etre
declenchee automatiquement a chaque sollicitation IA.

Commandes de reference :

```bash
docker compose exec api php bin/console lint:container
docker compose exec api composer analyse
docker compose exec api composer cs:check
docker compose exec api composer deptrac
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

Le script rapide agrege les controles non destructifs et evite la suite PHPUnit complete :

```bash
docker compose exec api composer quality:fast
```

La CI doit executer l'equivalent dans un environnement reproductible uniquement pour les
validations pre-merge et les merges vers `develop`. Les branches de travail peuvent se
limiter a lint, validation de conteneur, schema cible ou tests PHPUnit cibles selon le
scope du changement.

## Outillage statique

- `composer analyse` lance PHPStan avec les extensions Symfony, Doctrine, PHPUnit et strict rules.
- `composer cs:check` verifie le formatage PHP via PHP-CS-Fixer sans modifier les fichiers.
- `composer cs:fix` applique les corrections PHP-CS-Fixer.
- `composer deptrac` controle les dependances entre couches applicatives.
- `composer rector:check` liste les modernisations Rector possibles en dry-run.
- `composer infection` lance le mutation testing. Cette commande est couteuse et reste separee
  du script `quality:fast`. Les seuils MSI sont initialises a `0` tant qu'aucune baseline
  mutationnelle stable n'a ete etablie ; les augmenter progressivement avec les tests dedies.

Le conteneur `api` embarque PCOV pour permettre a PHPUnit et Infection de produire la
couverture de code. Composer n'applique pas de timeout global aux scripts afin de ne pas
interrompre la suite PHPUnit longue pendant `composer quality`.

PHPStan et Deptrac utilisent des baselines (`phpstan-baseline.neon`,
`deptrac-baseline.yaml`) pour documenter la dette existante. Toute nouvelle erreur non
presente dans ces baselines doit etre traitee dans le scope du changement.
