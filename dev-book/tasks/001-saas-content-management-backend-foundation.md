# Task : Socle backend SaaS de gestion de contenu

## Metadonnees

- ID : TASK-001
- Source : brief utilisateur du 2026-05-15
- Statut : En cours
- Perimetre : API Symfony 8 pour plateforme SaaS multi-clients et multi-projets
- Dossier cible : `api/symfony-8-api-platform/`
- Premier consommateur cible : `modern-web-apps/mlk-my-little-kasbah`

## Objectif

Creer le socle backend d'une plateforme SaaS de gestion de contenu capable de servir plusieurs organisations, plusieurs projets par organisation et plusieurs webapps clientes, avec un modele SQL structure, une API securisee par projet et une base extensible pour les workflows IA futurs.

Le MVP doit rester centre sur les fondations : tenancy, permissions, contenus generiques prioritaires, upload image et isolation stricte des donnees.

## Decision technique validee

- La version cible est Symfony 8.
- Le projet API doit etre initialise dans `api/symfony-8-api-platform/`.
- Aligner `composer.json`, Docker, documentation et CI sur Symfony 8 des l'initialisation du projet API.
- Toute reference a Symfony 7.4 LTS dans le brief initial est consideree comme obsolete pour cette task.
- Authentification MVP : `lexik/jwt-authentication-bundle` avec JWT stateless.
- Refresh token MVP : `gesdinet/jwt-refresh-token-bundle`.
- Authentification cible long terme : garder l'architecture compatible OAuth2 / OIDC externe.
- Upload MVP : `vich/uploader-bundle` avec ressource dediee `MediaObject`.
- Projet dockerise obligatoire : API Symfony 8, PostgreSQL, volumes d'upload et configuration dev doivent etre reproductibles via Docker Compose.
- Suppression contenu MVP : archiver via `deletedAt`, pas de suppression physique par defaut.
- Tests MVP : PHPUnit pour unitaires et tests API fonctionnels ; Behat hors MVP sauf besoin explicite de scenarios metier lisibles par non-devs.

## Perimetre MVP

### Niveau 1 - Socle SaaS

- `organization`
- `project`
- `user`
- `project_member`
- `media_object`
- `category_code`

### Niveau 2 - Contenus generiques

- `web_page`
- `article`
- `creative_work`
- `menu_item`
- `message`

## Hors perimetre MVP

- Implementation immediate des 19 schemas `schemas/webapp/`.
- Tables completes pour accommodation, offer, trip, real estate et autres modeles long terme.
- Analyse IA effective des images.
- Generation automatique de miniatures si le pipeline image n'est pas encore stabilise.
- Modification directe des contenus finaux par IA sans validation humaine.

## Contraintes d'architecture

- Utiliser PostgreSQL comme stockage principal.
- Modeliser le coeur metier en SQL structure, sans architecture JSON fourre-tout.
- Reserver `JSONB` aux metadonnees secondaires, enrichissements IA, donnees Schema.org flexibles et champs evolutifs.
- Toutes les donnees metier doivent etre rattachees a un `project`.
- `project` porte la relation vers `organization`.
- Toutes les routes metier doivent etre pensees sous la forme `/api/projects/{projectId}/...`.
- Toute lecture ou ecriture doit etre filtree automatiquement par projet.
- Un utilisateur ne doit jamais pouvoir acceder aux donnees d'un projet dont il n'est pas membre.
- Ne pas coupler le domaine a LexikJWT : l'identite courante doit etre isolee derriere un service applicatif.
- Ne pas coupler le domaine au filesystem ni a VichUploaderBundle : le stockage doit rester remplacable plus tard par S3, MinIO ou CDN.

## Audit et timestamps

Prevoir les champs d'audit des le socle, meme si certaines fonctionnalites ne sont pas exposees immediatement.

- `createdAt`
- `updatedAt`
- `createdBy`
- `updatedBy`
- `publishedAt`
- `deletedAt`

`deletedAt` prepare un soft delete eventuel. Il ne doit pas forcer l'activation immediate du soft delete si le comportement de suppression n'est pas encore tranche.

## Politique de suppression

- Les contenus CMS et medias ne sont pas supprimes physiquement par defaut.
- Le MVP utilise une suppression logique par `deletedAt` ou un statut d'archivage equivalent.
- Les requetes API standards doivent exclure les contenus archives/supprimes logiquement.
- Les fichiers physiques associes aux `MediaObject` ne sont pas effaces automatiquement au moment de l'archivage.
- Une suppression physique definitive reste une operation administrative separee, a specifier plus tard avec garde-fous.

Cette decision protege l'audit, la recuperation d'erreur, la synchronisation avec les webapps clientes et les workflows IA/CDN futurs.

## Strategie schemas, DTO et entites

- `schemas/webapp/` reste le modele metier canonique long terme.
- `schemas/dtos/symfony_api/` est la couche de contrats d'echange pour l'API Symfony.
- Les entites Doctrine peuvent diverger des schemas pour gerer persistance, tenancy, securite, statuts, audit, index et contraintes SQL.
- Les DTOs Symfony API doivent documenter explicitement les ecarts entre les schemas canoniques et les payloads HTTP reels.
- Les ressources API Platform ne doivent pas exposer aveuglement les entites Doctrine si un DTO input/output protege mieux le contrat.
- Les transformations cote webapp restent dans les mappers/services de la webapp consommatrice, pas dans les composants UI.

`modern-web-apps/mlk-my-little-kasbah` est le premier projet consommateur a prendre en compte. Cette webapp utilise deja les schemas partages au build et attend notamment `schemas/dtos/symfony_api/accommodation.yaml`, `schemas/dtos/symfony_api/web_page.yaml` et `schemas/webapp/`. L'API doit donc faire evoluer les DTOs Symfony API de maniere compatible avec ce pipeline.

## Strategie authentification

- Utiliser `lexik/jwt-authentication-bundle` pour le MVP.
- Utiliser `gesdinet/jwt-refresh-token-bundle` pour les refresh tokens du MVP.
- Considerer `gesdinet/jwt-refresh-token-bundle` comme un choix pragmatique MVP, a garder remplacable si la maintenance ou les besoins de securite evoluent.
- Exposer `POST /api/auth/login` pour l'authentification stateless.
- Exposer `POST /api/auth/refresh` pour le refresh token.
- Proteger les routes `/api/**` avec JWT.
- Stocker les refresh tokens cote serveur via le bundle retenu.
- Configurer expiration, invalidation et rotation selon une politique explicite.
- Prevoir la revocation des refresh tokens a la deconnexion ou en cas de compromission.
- Centraliser les droits projet via `ProjectMember`, voters et services applicatifs.
- Preparer une migration future vers un fournisseur OAuth2 / OIDC externe sans changer le modele metier.

Le JWT prouve l'identite de l'utilisateur. Il ne suffit pas a garantir le multi-tenant : l'autorisation reelle doit toujours verifier le membership et le role sur le projet cible.

## Strategie CORS

- Configurer CORS des le MVP pour les webapps clientes autorisees.
- En dev, autoriser explicitement les origines locales necessaires, notamment celle de `modern-web-apps/mlk-my-little-kasbah`.
- En prod, utiliser une allowlist stricte par environnement, jamais `*` avec authentification.
- Autoriser les headers requis pour JWT, refresh token si applicable, `Content-Type` et uploads `multipart/form-data`.
- Documenter les variables d'environnement CORS dans `.env.example`.
- Ajouter une validation manuelle ou automatisee pour verifier que preflight et requetes authentifiees fonctionnent depuis la webapp cliente.

## Strategie de tests

- Utiliser PHPUnit comme outil de test principal du MVP.
- Couvrir les entites, services applicatifs, voters et regles de tenancy par tests unitaires ou integration legers.
- Couvrir les endpoints critiques par tests fonctionnels API Platform.
- Prioriser les scenarios :
  - login et refresh token
  - refus sans JWT
  - isolation projet sur contenus texte
  - isolation projet sur `MediaObject`
  - upload image valide et invalide
  - filtrage des contenus archives via `deletedAt`
- Ne pas introduire Behat dans le MVP sauf si le projet a besoin de scenarios fonctionnels lisibles hors equipe dev.
- Cible minimale : les regles de securite multi-tenant, auth et upload doivent etre couvertes avant merge.

## Strategie upload image

- Utiliser `vich/uploader-bundle` pour le MVP.
- Implementer une ressource dediee `MediaObject`.
- Utiliser `POST multipart/form-data` pour l'upload.
- Route cible : `POST /api/projects/{projectId}/media-objects`.
- Limiter le MVP aux images.
- Valider MIME type, extension, taille et presence du fichier.
- Stockage dev : filesystem local.
- Stockage futur : garder une abstraction compatible S3, MinIO ou CDN.
- Retourner une URL exploitable par les clients via `contentUrl` ou champ equivalent.
- Enregistrer au minimum `filePath`, `mimeType`, `size`, `originalFilename`, `checksumSha256`, `status` et `project_id`.
- Calculer un checksum SHA256 a l'upload pour preparer detection de doublons, cache, synchronisation, CDN, IA et verification d'integrite.
- Creer le `MediaObject` avec `status=pending_analysis` pour preparer le workflow IA.

## Strategie Docker

- Fournir un environnement Docker local pour l'API Symfony 8.
- Inclure au minimum les services `php` ou `api`, `postgres` et les volumes necessaires.
- Prevoir un volume persistant PostgreSQL.
- Prevoir un volume ou dossier monte pour les uploads en dev.
- Exposer l'API sur un port local documente.
- Fournir les variables d'environnement via `.env.example`, sans secret commite.
- Documenter les commandes de demarrage, migrations, tests et logs dans le README API.
- Garder la compatibilite avec le repo orchestrateur racine et ses fichiers Compose existants.
- Eviter de dupliquer inutilement la configuration : le compose local de l'API doit rester autonome, le compose racine doit seulement orchestrer si necessaire.

## Architecture cible

- `Organization` possede plusieurs `Project`.
- `Project` porte les contenus et medias.
- `ProjectMember` porte le role et les permissions d'un `User` sur un projet.
- Les contextes minimum a separer :
  - Tenant / Organization
  - Identity / Access
  - Content
  - Media
  - Shared / Common

L'architecture doit rester compatible avec une approche hexagonale ou DDD sobre : domaine lisible, application/use cases, infrastructure Doctrine/API Platform, exposition HTTP.

## Plan d'execution

- [ ] Initialiser ou aligner le projet API Symfony 8 cible dans `api/symfony-8-api-platform/`.
- [ ] Configurer PHP, Symfony, API Platform, Doctrine ORM, PostgreSQL et Docker.
- [ ] Creer le Dockerfile API Symfony 8.
- [ ] Creer le `docker-compose.yml` local de l'API avec API/PHP, PostgreSQL, volumes et reseau.
- [ ] Ajouter `.env.example` pour l'API, PostgreSQL, JWT, upload et URLs publiques.
- [ ] Documenter comment lancer l'API seule depuis `api/symfony-8-api-platform/`.
- [ ] Documenter comment l'orchestrateur racine peut lancer ou composer cette API sans casser l'autonomie du projet.
- [ ] Installer et configurer `lexik/jwt-authentication-bundle` pour le JWT MVP.
- [ ] Installer et configurer `gesdinet/jwt-refresh-token-bundle`.
- [ ] Configurer expiration, rotation et revocation des refresh tokens.
- [ ] Verifier la maintenance et la compatibilite Symfony 8 du bundle refresh token au moment de l'installation.
- [ ] Installer et configurer `vich/uploader-bundle` pour les uploads image.
- [ ] Definir les entites et migrations du niveau 1 : `Organization`, `Project`, `User`, `ProjectMember`, `MediaObject`, `CategoryCode`.
- [ ] Definir les relations de tenancy : `Project.organization`, contenus rattaches a `Project`, memberships rattaches a `Project`.
- [ ] Ajouter les champs d'audit et timestamps pertinents aux entites metier MVP.
- [ ] Definir la strategie de remplissage de `createdAt`, `updatedAt`, `createdBy` et `updatedBy`.
- [ ] Ajouter `publishedAt` sur les contenus publiables.
- [ ] Ajouter `deletedAt` pour preparer un soft delete futur sans l'activer si non necessaire.
- [ ] Implementer la suppression logique par `deletedAt` ou archivage pour les contenus CMS et medias.
- [ ] Exclure par defaut les contenus archives/supprimes logiquement des collections API.
- [ ] Mettre en place JWT access token et refresh token.
- [ ] Exposer `POST /api/auth/login`.
- [ ] Exposer `POST /api/auth/refresh`.
- [ ] Documenter comment l'architecture reste compatible OAuth2 / OIDC externe.
- [ ] Configurer CORS par environnement avec allowlist explicite.
- [ ] Ajouter les origines dev necessaires pour `modern-web-apps/mlk-my-little-kasbah`.
- [ ] Implementer les roles par projet dans `ProjectMember`.
- [ ] Ajouter les controles d'acces centralises : ownership, membership, role par projet.
- [ ] Implementer le filtrage automatique des collections et items par `projectId`.
- [ ] Exposer les premieres routes API Platform sous `/api/projects/{projectId}/...`.
- [ ] Implementer les entites niveau 2 : `WebPage`, `Article`, `CreativeWork`, `MenuItem`, `Message`.
- [ ] Brancher les modeles sur les schemas disponibles dans `schemas/webapp/` sans chercher a tout implementer.
- [ ] Formaliser les DTOs d'API dans `schemas/dtos/symfony_api/` pour les ressources MVP exposees.
- [ ] Documenter pour chaque DTO l'ecart utile avec `schemas/webapp/` quand l'entite Doctrine ou le payload API differe.
- [ ] Verifier la compatibilite initiale avec `modern-web-apps/mlk-my-little-kasbah`, notamment les DTOs `web_page` et `accommodation` deja consommes.
- [ ] Implementer l'upload image MVP avec `POST multipart/form-data` : validation image, stockage, creation `MediaObject`.
- [ ] Calculer et stocker `checksumSha256` pour chaque image uploadee.
- [ ] Ajouter le statut media `pending_analysis` pour preparer le workflow IA.
- [ ] Ajouter les champs IA separes des champs finaux : `aiSuggestedFilename`, `aiSuggestedAlt`, `aiSuggestedDescription`, `finalFilename`, `finalAlt`, `finalDescription`, `aiReviewStatus`.
- [ ] Preparer un message Symfony Messenger apres upload image, sans traitement IA reel dans le MVP si non pret.
- [ ] Documenter les routes, le modele de permissions et les decisions de version dans le README API.
- [ ] Ajouter une suite PHPUnit couvrant les regles critiques du MVP.

## Validations attendues

- [ ] Les migrations Doctrine s'executent sur PostgreSQL depuis un environnement propre.
- [ ] L'API demarre depuis Docker Compose local.
- [ ] PostgreSQL demarre avec volume persistant en dev.
- [ ] Les uploads image fonctionnent avec le volume ou dossier dev documente.
- [ ] Les variables sensibles sont absentes du repo et representees uniquement dans `.env.example`.
- [ ] Les endpoints projet utilisent le format `/api/projects/{projectId}/...`.
- [ ] Un utilisateur non membre d'un projet recoit un refus explicite sur lecture et ecriture.
- [ ] Un utilisateur membre d'un projet ne voit jamais les donnees d'un autre projet.
- [ ] Un utilisateur ne peut pas acceder a un `MediaObject` rattache a un autre projet ; la reponse doit etre `403` ou `404` selon la strategie de non-divulgation retenue.
- [ ] Une requete sans JWT valide ne peut pas acceder aux routes `/api/projects/{projectId}/...`.
- [ ] `POST /api/auth/login` retourne un access token et un refresh token valides.
- [ ] `POST /api/auth/refresh` retourne un nouvel access token depuis un refresh token valide.
- [ ] Un refresh token valide permet d'obtenir un nouvel access token.
- [ ] Un refresh token expire, revoque ou invalide est refuse.
- [ ] La rotation et la revocation des refresh tokens sont couvertes par des tests fonctionnels dedies.
- [ ] Les entites MVP sont typées strictement et sans champ `any` equivalent cote contrats.
- [ ] Les entites metier MVP exposent des timestamps d'audit coherents.
- [ ] `createdBy` et `updatedBy` sont renseignes depuis l'utilisateur authentifie quand l'action est utilisateur-dependante.
- [ ] Les contenus archives via `deletedAt` ne ressortent pas dans les collections API standards.
- [ ] Une suppression logique ne supprime pas physiquement le fichier d'un `MediaObject`.
- [ ] Les champs `JSONB` sont justifies et limites aux usages evolutifs.
- [ ] L'upload refuse les fichiers non image.
- [ ] L'upload utilise `POST multipart/form-data`.
- [ ] La creation d'image produit un `MediaObject` rattache au bon projet.
- [ ] La creation d'image renseigne un `checksumSha256` stable pour un meme fichier.
- [ ] La reponse d'upload expose une URL client exploitable.
- [ ] Les DTOs `schemas/dtos/symfony_api/` couvrent les payloads reels exposes par l'API MVP.
- [ ] `modern-web-apps/mlk-my-little-kasbah` peut continuer a resoudre les schemas partages necessaires a son build.
- [ ] Les tests couvrent au minimum isolation projet, permissions, acces inter-projets aux `MediaObject` et upload image.
- [ ] Les tests PHPUnit couvrent les endpoints critiques auth, tenancy, media upload et soft delete.
- [ ] Les preflight CORS et appels authentifies depuis une origine cliente autorisee fonctionnent en dev.

## Points de vigilance

- Securite : l'isolation projet doit etre centralisee, testee et impossible a contourner par un filtre oublie.
- Securite medias : les fichiers uploades suivent les memes regles de tenancy que les contenus texte ; aucune URL ou operation ne doit exposer un media d'un autre projet.
- Auth : LexikJWT est le choix MVP, pas une dependance metier long terme.
- Refresh token : `gesdinet/jwt-refresh-token-bundle` est le choix MVP ; sa politique d'expiration, rotation et revocation doit etre documentee.
- Refresh token : le bundle retenu doit rester une dependance d'infrastructure remplacable ; ne pas coupler les regles metier a son API interne.
- Contrats : les schemas `schemas/webapp/` sont une cible long terme, pas une obligation de generation immediate.
- Performance : prevoir indexes sur `project_id`, `organization_id`, `user_id` et les champs de publication/recherche utiles.
- Audit : timestamps et auteurs doivent etre disponibles tot pour tracer les changements CMS, meme si le soft delete reste inactif au depart.
- Suppression : privilegier archivage et recuperabilite ; toute purge physique doit rester explicite et separee.
- Tests : ne pas limiter la verification a des tests unitaires de domaine ; les chemins API critiques doivent etre testes.
- CORS : eviter les configurations permissives qui masquent les problemes en dev et deviennent dangereuses en prod.
- Maintenabilite : ne pas dupliquer la logique d'autorisation dans chaque controller si un voter, provider, extension Doctrine ou service applicatif peut la centraliser proprement.
- IA : les suggestions IA restent separees des valeurs finales et doivent passer par validation humaine.
- Upload : VichUploaderBundle est le choix MVP, mais le domaine doit rester independant du filesystem et du bundle.
- Integrite media : le checksum SHA256 doit etre calcule cote backend et ne pas dependre d'une valeur fournie par le client.
- Docker : le sous-projet API doit rester autonome, meme si le repo racine peut l'orchestrer.
