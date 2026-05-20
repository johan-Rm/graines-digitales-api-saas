# Task : Fixtures multi-clients et multi-projets

## Metadonnees

- ID : TASK-004
- Source : ajustement utilisateur du 2026-05-19
- Statut : MVP implemente
- Perimetre : donnees de demonstration et tests pour tenancy SaaS
- Dependance fonctionnelle : TASK-001

## Objectif

Creer des fixtures reproductibles pour tester et demontrer le modele SaaS :
plusieurs organisations, plusieurs projets, plusieurs utilisateurs, memberships et contenus
isoles par projet.

## Perimetre MVP

- Au moins deux organisations.
- Au moins deux projets par organisation.
- Plusieurs utilisateurs avec roles differents.
- Memberships explicites par projet.
- Contenus `WebPage`, `Article`, `MenuItem`, `CategoryCode`.
- Quelques `MediaObject` de demonstration si le pipeline upload est pret.

## Contraintes

- Aucune donnee sensible reelle.
- Donnees lisibles et coherentes avec le produit immobilier Essaouira.
- Fixtures stables pour les tests automatises.
- Les UUIDs peuvent etre fixes si cela simplifie les tests.

## Plan d'execution

- [x] Choisir l'outil de fixtures compatible Symfony 8.
- [x] Definir les organisations de demonstration.
- [x] Definir les projets par organisation.
- [x] Definir les utilisateurs et roles projet.
- [x] Ajouter les contenus minimaux par projet.
- [ ] Ajouter des UUIDs stables pour les scenarios critiques.
- [x] Documenter comment charger les fixtures.
- [ ] Brancher les fixtures utiles aux tests fonctionnels.

## Validations attendues

- [x] Les fixtures se chargent depuis un environnement propre.
- [x] Les projets sont correctement rattaches a leur organisation.
- [x] Les memberships permettent de tester les refus inter-projets.
- [x] Les contenus de deux projets restent distincts.
- [ ] Les tests peuvent s'appuyer sur des donnees stables.
