# Task : Durcissement du mutation testing Infection

## Metadonnees

- ID : TASK-026
- Source : retour execution locale Infection du 2026-06-05
- Statut : En cours
- Perimetre : tests PHPUnit, Infection, couverture mutationnelle, qualite backend
- Dependances fonctionnelles : TASK-005, TASK-020

## Objectif

Transformer Infection d'un outil disponible en audit exploitable et progressivement
bloquant, sans casser la boucle de validation locale.

Le premier run complet confirme que l'environnement est operationnel :

- PCOV est active dans le conteneur `api`
- PHPUnit genere la couverture de `src/`
- Infection va au bout et produit ses rapports
- la couverture mutationnelle des mutants couverts est deja elevee

Reference locale mesuree :

```text
2316 mutations generated
347 mutants killed by Test Framework
19 covered mutants not detected
1 timeout
1949 mutants required more time than configured
Mutation Code Coverage: 100%
Covered Code MSI: 94%
```

## Baseline V1 apres durcissement

Premier durcissement applique :

- correction de l'independance de `ProjectIsolationTest` en ordre aleatoire
- ajout de tests unitaires sur les invariants `Article`
- ajout de tests unitaires sur les chemins dangereux de `ProjectSeedImageSynchronizer`
- seuil `minCoveredMsi` releve a `90`

Nouvelle reference locale minimale observee :

```text
2319 mutations generated
199+ mutants killed by Test Framework
12 covered mutants not detected
2-4 timeouts
2022+ mutants required more time than configured
Mutation Code Coverage: 100%
Covered Code MSI: 94-95%
```

Les 12 mutants echappes restants sont concentres sur `ProjectSeedImageSynchronizer`.
Ils concernent surtout des normalisations de chemins equivalentes sous Linux
(`rtrim`, `trim`, doubles slashs) et des details de creation de dossier difficiles a
observer sans tests fragiles lies aux permissions du systeme de fichiers.

Les timeouts restants sont hors du lot traite et peuvent varier selon la machine :

- `CreateSuperAdminCommand::resolvePassword`
- `BulkMediaUploadController` sur la normalisation des violations de validation

Le seuil `minCoveredMsi` reste fixe a `90` pour tenir compte de cette variabilite tout en
rendant `make infection` bloquant en cas de regression significative.

## Perimetre MVP

- Analyser les mutants echappes du rapport Infection.
- Classer les mutants par criticite fonctionnelle et risque securite.
- Ajouter les tests manquants uniquement la ou le mutant revele un comportement important.
- Identifier les faux positifs acceptables et les documenter.
- Stabiliser la configuration pour reduire les timeouts non pertinents.
- Remonter progressivement les seuils `minMsi` et `minCoveredMsi`.

## Hors perimetre

- Ne pas chercher a tuer 100% des mutants en une seule passe.
- Ne pas rendre `make infection` bloquant dans la boucle quotidienne.
- Ne pas refactorer le domaine uniquement pour satisfaire Infection.
- Ne pas affaiblir les tests d'isolation inter-projets pour accelerer la commande.

## Pistes techniques a evaluer

1. Lancer Infection sur un sous-perimetre critique avec `--filter` ou une configuration dediee.
2. Prioriser les mutants sur les couches sensibles :
   - `src/Security/`
   - `src/State/`
   - providers/processors project-scoped
   - commandes et services d'administration
3. Ajouter des tests unitaires rapides sur les services purs quand un test fonctionnel complet
   serait trop couteux.
4. Ajuster `timeout` dans `infection.json5` si des mutants valides expirent trop vite.
5. Exclure explicitement les mutations sans valeur metier lorsque le faux positif est prouve.

## Plan d'execution

- [x] Lire `var/infection/summary.log` et `var/infection/infection.log`.
- [x] Lister les mutants echappes par fichier, mutateur et criticite.
- [ ] Traiter en priorite les mutants lies a la securite multi-tenant et aux contrats API.
- [x] Ajouter ou renforcer les tests PHPUnit cibles.
- [ ] Relancer `make test` apres chaque lot de corrections.
- [x] Relancer `make infection` sur le perimetre concerne.
- [x] Documenter les faux positifs acceptes et les exclusions eventuelles.
- [x] Remonter `minCoveredMsi` a un premier seuil realiste.
- [ ] Decider si une commande `make infection-critical` est utile pour la CI.

## Validations attendues

- [ ] `make quality-fast` passe.
- [ ] `make test` passe.
- [ ] `make infection` passe avec PCOV actif.
- [ ] Le nombre de mutants echappes critiques diminue.
- [ ] Les seuils Infection refletent une baseline assumee, pas un objectif arbitraire.

## Points de vigilance

- Le mutation testing est couteux : il doit completer la strategie de tests, pas remplacer
  les tests fonctionnels d'isolation inter-projets.
- Un mutant echappe n'est pas toujours un bug ; il peut aussi signaler du code sans impact,
  une mutation equivalente ou une responsabilite mal exprimee.
- Les seuils ne doivent devenir bloquants qu'une fois la baseline stabilisee.
- Les changements de tests sur les chemins project-scoped doivent proteger explicitement
  l'isolation inter-tenants.
