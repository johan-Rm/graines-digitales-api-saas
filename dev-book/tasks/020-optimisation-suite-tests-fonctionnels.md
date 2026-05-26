# Task : Optimisation de la suite de tests fonctionnels

## Metadonnees

- ID : TASK-020
- Source : retour execution locale du 2026-05-25
- Statut : A faire
- Perimetre : PHPUnit, tests fonctionnels API Platform, base de test PostgreSQL, logs test
- Dependances fonctionnelles : TASK-005

## Objectif

Reduire fortement le temps d'execution de la suite PHPUnit sans affaiblir les garanties
critiques du projet : isolation inter-projets, reproductibilite des fixtures et absence de
dependance a des donnees locales manuelles.

Le diagnostic actuel est que `FunctionalTestCase::setUp()` recree tout le schema Doctrine
et recharge les fixtures demo avant chaque test fonctionnel. Cette strategie est simple et
fiable, mais elle rend la suite complete lente a mesure que le nombre de scenarios augmente.

## Perimetre MVP

- Mesurer le temps de reference de la suite complete et des classes fonctionnelles les plus
  lentes.
- Reduire le bruit des logs en environnement `test`.
- Remplacer le reset schema + fixtures par test par une strategie plus rapide.
- Conserver une isolation forte entre tests pour eviter les fuites d'etat.
- Documenter la nouvelle strategie de test et ses limites.

## Pistes techniques a evaluer

1. Transactions avec rollback autour de chaque test fonctionnel.
2. Snapshot SQL de base prechargee puis restauration rapide entre tests ou classes.
3. Reset schema + fixtures une seule fois par classe lorsque les tests ne mutent pas le
   meme etat critique.
4. Suites PHPUnit separees : `unit`, `functional-critical`, `functional-full`.

La solution retenue doit rester compatible avec Docker et CI.

## Contraintes

- Ne pas reduire la couverture des tests d'isolation inter-projets.
- Ne pas rendre les tests dependants de l'ordre d'execution.
- Ne pas masquer les erreurs Doctrine ou API Platform utiles au debug.
- Ne pas introduire de dependance lourde si une solution Symfony/Doctrine standard suffit.
- La base `graines_digitales_api_test` doit pouvoir etre creee automatiquement ou documentee
  clairement pour un setup local reproductible.

## Plan d'execution

- [ ] Mesurer le temps actuel de `docker compose exec api php bin/phpunit`.
- [ ] Identifier les classes les plus couteuses.
- [ ] Desactiver ou router les logs debug verbeux en environnement `test`.
- [ ] Prototyper une strategie d'isolation plus rapide.
- [ ] Verifier que les tests restent independants via execution aleatoire ou relance ciblee.
- [ ] Mettre a jour `docs/09-quality/testing-strategy.md`.
- [ ] Ajouter les commandes utiles dans la documentation de validation.

## Validations attendues

- [ ] `docker compose exec api php bin/phpunit` passe.
- [ ] `docker compose exec api php bin/console lint:container` passe.
- [ ] `docker compose exec api php bin/console doctrine:schema:validate` passe.
- [ ] La suite complete est significativement plus rapide que la reference mesuree.
- [ ] Les tests critiques d'isolation inter-projets restent couverts.

## Points de vigilance

- Une strategie transactionnelle peut etre insuffisante si certains tests ferment la
  connexion Doctrine, declenchent des commits implicites ou passent par des processus
  separes.
- Un snapshot SQL doit rester synchronise avec les fixtures et le schema Doctrine.
- Un reset par classe peut accelerer fortement, mais seulement si les tests d'une meme
  classe ne se polluent pas mutuellement.
