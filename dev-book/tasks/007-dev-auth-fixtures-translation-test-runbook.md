# TASK-007 - Runbook dev pour tester authentification et traductions

## Metadonnees

- ID : TASK-007
- Source : echanges utilisateur du 2026-05-19
- Statut : A faire
- Perimetre : DevEx locale, donnees de test, authentification JWT, test manuel des traductions
- Dependances fonctionnelles : TASK-001, TASK-002

## Objectif

Fournir un chemin simple, reproductible et documente pour tester localement les routes
protegees et les traductions de contenus sans manipulations SQL fragiles a chaque session.

Un developpeur doit pouvoir :

- creer ou reinitialiser un utilisateur local de test
- creer ou recuperer un projet local de test
- associer l'utilisateur au projet avec un role permettant l'ecriture
- obtenir un token JWT
- creer une ressource traduisible
- ajouter une traduction cible
- verifier la lecture localisee et le fallback

## Probleme actuel

Le projet expose `POST /api/auth/login` et TASK-006 ajoute un endpoint local/test pour
creer un utilisateur. Il manque encore un chemin complet pour preparer les donnees de
test projet :

- fixtures locales completes
- commande de seed dev
- runbook clair pour obtenir un JWT et un `projectId`
- scenario documente pour tester `GET ?locale=...` et les routes `/translations`

Le test manuel oblige donc a inserer un user, un membership projet et parfois des donnees
metier directement en SQL.

## Perimetre MVP

- Ajouter une commande Symfony de seed dev, par exemple :
  - `app:dev:seed-auth`
  - ou `app:dev:seed-demo-project`
- Creer ou reutiliser :
  - une organization de demo
  - un projet de demo
  - un user admin/editor local
  - un `ProjectMember` avec role `owner` ou `editor`
- Afficher en sortie :
  - email
  - mot de passe dev
  - `projectId`
  - commandes `curl` utiles
- Documenter la recuperation du token JWT.
- Documenter un scenario complet de test de traduction.

## Hors perimetre MVP

- Endpoint public d'inscription.
- Gestion production des comptes utilisateurs.
- UI backoffice.
- Fixtures exhaustives de tous les contenus.
- Strategie CI complete, couverte par TASK-005.

## Contraintes

- Commande disponible uniquement en environnement `dev` ou `test`.
- Ne jamais introduire de secret de production.
- Mot de passe local explicite et non ambigu, reserve au dev.
- Commande idempotente : relancable sans creer de doublons.
- Aucun contournement des regles projet : les tests doivent passer par JWT et membership.
- Ne pas dependre d'un UUID hardcode si une donnee existante peut etre retrouvee proprement.

## Scenario utilisateur cible

1. Lancer Docker.
2. Executer la commande de seed.
3. Copier le `projectId` affiche.
4. Obtenir un token via :

```bash
curl -X POST http://localhost:18080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.test","password":"admin123"}'
```

5. Creer une page source en `fr`.
6. Ajouter une traduction `en`.
7. Lire la page avec `?locale=en`.
8. Lire une locale activee mais absente pour verifier le fallback explicite.

## Plan d'execution

- [ ] Creer une commande Symfony dev idempotente pour seed user, organization, project et membership.
- [ ] Hasher le mot de passe via le hasher Symfony, pas via un hash statique.
- [ ] Garantir que le user obtient au minimum `ROLE_ADMIN` ou un role compatible avec les routes de gestion.
- [ ] Garantir que le membership projet donne un role `owner` ou `editor`.
- [ ] Ajouter une option pour choisir email, mot de passe et slug projet si utile.
- [ ] Afficher les commandes `curl` de login et de test traduction en fin de commande.
- [ ] Documenter le runbook dans `README.md` ou `docs/`.
- [ ] Verifier le scenario manuellement sur Docker.
- [ ] Ajouter un test simple de la commande si le cout reste raisonnable.

## Validations attendues

- [ ] Un developpeur peut obtenir un token JWT en moins de deux commandes apres migration.
- [ ] Le `projectId` de test est visible sans requete SQL manuelle.
- [ ] Le token permet d'appeler les routes `/api/projects/{projectId}/...`.
- [ ] Une `WebPage` peut etre creee en `fr`.
- [ ] Une traduction `en` peut etre ajoutee via `/translations/en`.
- [ ] La lecture `?locale=en` retourne la traduction anglaise.
- [ ] Le fallback d'une locale activee mais absente est visible dans la reponse.
- [ ] La documentation ne mentionne pas de secret production ni de manipulation risquee.
