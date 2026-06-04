# Task : Moteur de validation des conventions techniques

## Metadonnees

- ID : TASK-024
- Source : brief architecture qualite, 2026-05-31
- Statut : A faire
- Perimetre : scripts CI, regles YAML, qualite du code source PHP/Symfony
- Dependances fonctionnelles : aucune

## Objectif

Mettre en place un moteur de validation CI/CD pilote par des regles YAML afin de detecter
les derives architecturales et reduire la dette technique de facon incrementale et
documentee.

Le systeme suit le modele :

```
README → Documentation → YAML → CI/CD
```

- Les conventions techniques sont decrites brievement dans les README.
- Les explications detaillees et exemples sont documentees dans `docs/`.
- Les regles de validation sont declarees dans des fichiers YAML.
- Les scripts CI lisent les regles YAML et executent les controles.

L'approche est volontairement progressive : commencer par quelques regles simples pour
valider le concept avant d'ajouter les regles specifiques au projet.

## Structure d'une regle YAML

```yaml
name: string              # identifiant unique kebab-case
description: string       # ce que la regle detecte
families:                 # une ou plusieurs familles de controle
  - Lisibilite
severity: error | warning | info
requiresManualReview: bool  # false = deterministe, true = heuristique
rule:                     # parametres du controle (seuils, patterns, etc.)
message: string           # message affiché en cas de violation
```

### Familles de controles

| Famille | Exemples de regles |
|---|---|
| Lisibilite | taille des fonctions, taille des fichiers |
| Clean Code | TODO sans contexte, duplication |
| Architecture & Decouplage | dependances circulaires, couplage fort |
| Robustesse & Fiabilite | exceptions silencieuses, parametres excessifs |
| Performance & Stabilite | queries N+1 detectables, eager loading systematique |
| Tests | classes sans tests, couverture minimale |
| Documentation | methodes publiques non documentees |
| Gouvernance Technique | fichiers interdits commites, secrets en clair |

### Types de regles

- **Deterministes** (`requiresManualReview: false`) : le script peut statuer seul (compter
  des lignes, detecter un pattern regex, analyser les imports). Bloquant en CI possible.
- **Heuristiques** (`requiresManualReview: true`) : le script signale, mais un humain tranche
  (duplication semantique, couplage fort non trivial). Rapport uniquement, jamais bloquant.

## Perimetre MVP

Valider le concept avec un jeu de regles simples avant toute regle projet-specifique :

1. **Taille des fonctions** — methodes depassant N lignes (ex. 40)
2. **Taille des fichiers** — fichiers depassant N lignes (ex. 300)
3. **Dependances circulaires** — detection via analyse des imports/use PHP
4. **TODO sans contexte** — `TODO`, `FIXME`, `HACK` sans reference ticket
5. **Duplication de blocs** — blocs de code identiques de M lignes ou plus (heuristique)
6. **Parametres excessifs** — fonctions avec plus de N parametres (ex. 5)

## Architecture technique attendue

```
scripts/quality/
├── validate.php          # point d'entree CLI
├── Engine.php            # moteur : charge les YAML, execute les regles, produit le rapport
├── Rule/
│   ├── RuleInterface.php
│   ├── FileSizeRule.php
│   ├── FunctionSizeRule.php
│   ├── CircularDependencyRule.php
│   ├── TodoWithoutContextRule.php
│   ├── ExcessiveParametersRule.php
│   └── DuplicationRule.php
└── rules/
    ├── readability.yaml
    ├── clean-code.yaml
    └── architecture.yaml
```

Le moteur doit produire :
- une sortie console avec les violations groupees par severite
- un rapport JSON optionnel (`--output=json`)
- un code de sortie non nul si des violations `error` sont trouvees

## Commandes attendues

```bash
# Lancer la validation complete
docker compose exec api php scripts/quality/validate.php src/

# Limiter a une famille
docker compose exec api php scripts/quality/validate.php src/ --family=Lisibilite

# Sortie JSON pour CI
docker compose exec api php scripts/quality/validate.php src/ --output=json > report.json

# Rapport seul, sans echec CI (pour les regles heuristiques)
docker compose exec api php scripts/quality/validate.php src/ --only-report
```

## Contraintes

- Pas de dependance externe lourde : le moteur doit tourner avec PHP seul ou avec les
  packages deja presents dans `composer.json`.
- Les regles YAML sont la source de verite : ajouter une regle ne doit pas necessiter de
  modifier le moteur.
- Les regles heuristiques ne doivent jamais etre bloquantes en CI.
- Le moteur doit etre testable unitairement.
- Les seuils par defaut dans les YAML doivent etre surchargeables par projet.

## Plan d'execution

- [ ] Creer la structure `scripts/quality/` avec `validate.php` et `Engine.php`.
- [ ] Definir `RuleInterface` et le mecanisme de chargement des YAML.
- [ ] Implementer les 6 regles MVP.
- [ ] Ecrire les fichiers `rules/*.yaml` avec les seuils par defaut.
- [ ] Tester le moteur sur `src/` du projet courant.
- [ ] Documenter les violations detectees et ajuster les seuils si necessaire.
- [ ] Ajouter la commande dans le `Makefile` (`make quality-rules`).
- [ ] Documenter le systeme dans `docs/09-quality/validation-engine.md`.
- [ ] Valider que le code de sortie est exploitable dans GitHub Actions.

## Validations attendues

- [ ] `php scripts/quality/validate.php src/` termine sans erreur fatale.
- [ ] Au moins 3 violations reelles detectees sur le code existant (preuve que les regles
      fonctionnent).
- [ ] Le rapport JSON est valide et parseable.
- [ ] Les regles heuristiques n'echouent pas le script meme en presence de violations.
- [ ] `make quality-rules` est disponible et documente.

## Points de vigilance

- La detection de dependances circulaires en PHP est heuristique si on se base uniquement
  sur les `use` statements : les injections de dependances Symfony peuvent creer des cycles
  non visibles statiquement.
- La detection de duplication par blocs de lignes identiques produit des faux positifs sur
  les getters/setters generes. Prevoir un mecanisme d'exclusion de patterns.
- Les seuils par defaut doivent refleter le code existant pour ne pas generer un bruit
  trop important des le premier lancement.
- Une integration SonarQube ulterieure est compatible avec cette approche : les deux peuvent
  coexister, SonarQube etant plus puissant sur la duplication et la couverture, le moteur
  YAML etant plus leger et projet-specifique.
