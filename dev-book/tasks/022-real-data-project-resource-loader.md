# Task : Remplacer les fixtures demo par un chargeur de donnees reelles projet

## Metadonnees

- ID : TASK-022
- Source : demande utilisateur du 2026-05-26
- Statut : A faire
- Perimetre : commande Symfony, import de donnees, ressources par projet, configuration `.env`
- Dependances fonctionnelles : TASK-004, TASK-008, TASK-010, TASK-021

## Constat

La commande actuelle a supprimer :

```bash
docker compose exec api php bin/console app:fixtures:load-demo
```

charge des fixtures de demonstration codees dans l'application. Ce fonctionnement etait
utile pour stabiliser le developpement local et les tests fonctionnels, mais il ne
correspond plus au besoin cible : charger de vraies donnees et des ressources associees a
chaque projet depuis un emplacement connu par la configuration d'environnement.

Le chargement doit rester idempotent, project-scoped et compatible avec l'isolation
multi-tenant.

La commande ne doit pas connaitre a l'avance la liste des projets disponibles. Elle charge
un seul projet a la fois, cible par un slug passe en argument.

La tache doit aussi servir de point de controle entre trois sources de verite en cours
d'alignement :

- le dossier source de donnees dans `/home/johan/www/graines-digitales/seed/webapp`
- le modele backend expose par l'API Symfony/API Platform
- les attentes du front consommateur

La V1 ne doit donc pas masquer les ecarts de donnees. Elle doit d'abord analyser le dossier
source, produire un rapport de validation exploitable, puis importer uniquement quand les
references critiques sont coherentes et que l'import a ete explicitement confirme.

## Objectif

Supprimer la commande `app:fixtures:load-demo` et la remplacer par une nouvelle commande :

```bash
app:seed:load-project
```

Cette commande charge des donnees reelles et des ressources pour un seul projet existant,
cible par son slug.

Les donnees et ressources a importer doivent etre lues depuis un chemin configure par `.env`,
et resolues avec le slug du projet passe a la commande :

```dotenv
SEED_PROJECT_PATH=/absolute/path/to/seed/webapp
```

```bash
docker compose exec api php bin/console app:seed:load-project mlk-modern-site-web
docker compose exec api php bin/console app:seed:load-project blue-bay-mogador
```

Le nom final de la variable devra etre valide pendant l'implementation.

## Perimetre V1

- Supprimer la commande `app:fixtures:load-demo`.
- Creer la commande `app:seed:load-project`.
- Ajouter un argument obligatoire `{projectSlug}` pour charger un seul projet a la fois.
- Lire un chemin racine d'import depuis la configuration d'environnement.
- Resoudre le dossier source avec `{SEED_PROJECT_PATH}/{projectSlug}`.
- Refuser l'import si le projet cible n'existe pas deja en base.
- Charger les contenus et ressources project-scoped depuis les fichiers presents dans ce
  dossier projet.
- Charger les ressources media associees aux projets depuis le meme espace de donnees.
- Charger les donnees dans cet ordre :
  1. metadata (`CategoryCode` et referentiels projet)
  2. images / `MediaObject`
  3. `WebPage`
  4. `Accommodation`
- Ne pas disperser cet ordre en dur dans la commande : l'ordre V1 doit etre le resultat
  d'un plan d'import calcule depuis les dependances declarees par chaque famille d'entites.
- Demarrer chaque import par une phase d'analyse/validation sans ecriture Doctrine.
- Demander une confirmation explicite avant toute ecriture lorsque la commande est lancee
  en mode interactif.
- Permettre en mode interactif de choisir les familles d'entites a importer apres le
  rapport d'analyse : metadata, medias, web-pages, accommodations ou tout importer.
- Valider les dependances entre familles avant import : `Accommodation` depend des metadata
  et souvent des medias ; `WebPage` depend des medias quand `associatedMedia` est present.
- Fournir une option non interactive explicite pour les environnements automatises, par
  exemple `--no-interaction --force`, sans jamais contourner le preflight.
- Fournir une option non interactive de selection explicite, par exemple
  `--only=metadata,media,web-pages,accommodations`.
- Les `Accommodation` doivent pouvoir reutiliser les metadata et medias charges
  precedemment comme references ou relations.
- Les `WebPage` ne doivent pas stocker de donnees residuelles dans `schemaData`.
- Maintenir l'idempotence : relancer la commande ne doit pas dupliquer les entites.
- Conserver l'isolation projet : chaque ressource importee doit etre rattachee au bon
  `Project`.
- Mettre a jour la documentation operationnelle qui mentionne encore des fixtures demo.

## Structure de donnees cible observee

La V1 doit partir de la structure source existante. La commande ne parcourt pas tous les
dossiers : elle resout uniquement le dossier correspondant au slug passe en argument.

```text
{SEED_PROJECT_PATH}/
  {projectSlug}/
    data/
      fr/
        metadata/
        web-pages/
        accommodations/
        ui/              # hors perimetre API V1
    resources/
      images/
```

Le dossier `ui/` doit etre ignore par le chargeur API. Il contient des donnees de libelles
ou de configuration frontend qui ne correspondent pas aux ressources backend project-scoped.

Analyse initiale du seed source :

- 2 dossiers projet : `mlk-modern-site-web`, `blue-bay-mogador`.
- 54 fichiers `accommodations/*.md` au total.
- 20 fichiers `web-pages/*.md` au total au moment de la derniere analyse.
- 4 fichiers `metadata/*.yaml` au total : `category-code.yaml` et `media-object.yaml` pour
  chaque projet.
- 281 images dans `resources/images/`.
- Les fichiers Markdown utilisent du front matter YAML et un body Markdown.

Analyse cible du dossier `blue-bay-mogador/data/fr/metadata` :

- `category-code.yaml` : 101 entrees, champs `codeValue`, `name`, `inCodeSet`, `text`
  optionnel.
  Repartition observee : 35 `tag`, 21 `amenity-feature`, 1 `article-section`,
  12 `accommodation-category`, 28 `accommodation-place`, 4 `real-estate-listing`.
  Le modele backend courant utilise `accommodation-type` pour la relation
  `Accommodation.category` ; la V1 normalise donc `accommodation-category` vers
  `accommodation-type` pendant l'import.
- `media-object.yaml` : 125 entrees, champs `url`, `mainEntity`, `caption` optionnel.
  Il n'y a plus d'`identifier` media source : la cle stable d'import doit etre l'URL
  normalisee ou le chemin fichier.
- Les 125 URLs media declarees ont un fichier physique correspondant dans
  `resources/images/`.
- 8 medias n'ont pas de `caption` ; l'import doit appliquer un fallback lisible depuis le
  nom de fichier ou accepter une description vide selon la regle retenue.
- Les `WebPage.associatedMedia` top-level utilisent des noms de fichiers. Dans l'etat
  analyse, 7 references de page Blue Bay ne sont pas declarees dans `media-object.yaml`.
  Ce point doit etre corrige dans le seed ou faire echouer l'import avec un message
  explicite.
- Les references `category`, `place` et `realEstateListing` des accommodations Blue Bay
  sont couvertes par les metadata.
- Les references `amenityFeature` des accommodations Blue Bay ne sont pas toutes couvertes
  par `inCodeSet = amenity-feature` : certaines valeurs existent comme `tag`, d'autres sont
  des libelles libres. La V1 doit etre stricte et signaler ces valeurs avant import.
- Les accommodations Blue Bay referencent encore certains medias via des alias de type
  `media-{basename}` et quelques UUID historiques. La V1 doit choisir explicitement entre :
  corriger le seed, accepter temporairement un alias `media-{basename}` documente, ou
  echouer strictement pendant le preflight.
- Le dossier `mlk-modern-site-web` contient aussi des ecarts a traiter : medias declares
  sans fichier physique local, quelques references de page non declarees dans
  `media-object.yaml`, et quelques amenities non couvertes par `amenity-feature`.

## Donnees a importer

- Ressources project-scoped :
  - `CategoryCode`
  - `WebPage`
  - `Accommodation`
  - `MediaObject`
  - relations utiles entre accommodations, categories, tags, amenities et medias

## Ordre de chargement obligatoire

0. Executer une analyse preflight stricte, sans ecriture :
   - verifier l'existence du projet en base et du dossier source resolu.
   - verifier la structure attendue : `data/fr/metadata`, `data/fr/web-pages`,
     `data/fr/accommodations`, `resources/images`.
   - parser tous les YAML et Markdown front matter avant toute ecriture.
   - lister les references manquantes avec fichier source et champ concerne.
   - echouer sans ecriture si une reference critique est absente ou ambigue.
   - afficher un resume lisible des donnees detectees : nombres de metadata, medias,
     pages, accommodations, fichiers ignores et avertissements non bloquants.
   - demander confirmation avant import si la commande est interactive.
   - proposer une selection des familles d'entites a importer si la commande est
     interactive, puis refuser toute selection dont les dependances ne sont ni selectionnees
     ni deja presentes en base.
1. Construire un plan d'import a partir des familles d'entites selectionnees et de leurs
   dependances. Pour la V1, le plan attendu aboutit a l'ordre suivant :
   metadata -> medias/images -> web-pages -> accommodations.
2. Charger les metadata :
   - `metadata/category-code.yaml`
   - chaque entree est importee comme `CategoryCode` rattache au projet :
     - `category-code.yaml` : `codeValue` alimente la cle metier interne persistante,
       `inCodeSet = inCodeSet`, `label = name`.
     - les entrees `accommodation-category` sont normalisees vers `accommodation-type`, et
       les entrees `accommodation-place` et
       `real-estate-listing` sont lues depuis ce meme fichier.
     - le champ `text` est conserve dans `CategoryCode.metadata` tant qu'aucun champ modele
       dedie n'existe.
3. Charger les images et `MediaObject` :
   - `metadata/media-object.yaml`
   - fichiers physiques dans `resources/images/`
   - cle d'idempotence : URL normalisee ou chemin fichier, pas un identifiant source.
   - `caption` alimente `finalAlt` et `finalDescription` quand disponible.
4. Charger les `WebPage` :
   - fichiers Markdown dans `data/fr/web-pages/`
   - champs importes en V1 : slug derive du nom de fichier, locale, `metaTitle`,
     `metaDescription`, `headline`, body Markdown.
   - le front matter `associatedMedia` alimente `WebPage.associatedMedia` avec les
     `MediaObject` charges a l'etape precedente.
   - les autres champs de front matter sont ignores tant qu'ils ne sont pas modelises.
5. Charger les `Accommodation` :
   - fichiers Markdown dans `data/fr/accommodations/`
   - relations vers `CategoryCode` et `MediaObject` resolues depuis les metadata deja
     chargees.
   - les references `category`, `place`, `realEstateListing` et `amenityFeature` sont
     validees contre leur `inCodeSet` respectif.

## Architecture du chargeur

La commande `app:seed:load-project` doit rester un orchestrateur. Elle ne doit pas porter
la logique detaillee d'import de chaque ressource.

Responsabilites de la commande :

- charger la configuration et resoudre le projet cible ;
- lancer l'analyse preflight ;
- collecter les familles d'entites disponibles et selectionnees ;
- construire le plan d'import depuis les dependances declarees ;
- demander confirmation ;
- executer les importers dans l'ordre calcule ;
- gerer transaction, erreurs et reporting global.

Chaque famille d'entites doit etre portee par un importer specialise qui declare au minimum :

- ce qu'il fournit, par exemple `category-code`, `media-object`, `web-page`,
  `accommodation` ;
- ce dont il depend, par exemple `accommodation` depend de `category-code` et
  `media-object` ;
- sa phase d'analyse sans ecriture ;
- sa phase d'import idempotente.

Le preflight construit un graphe de dependances entre familles d'entites et verifie que
chaque reference est resolvable soit dans les donnees selectionnees, soit dans les donnees
deja presentes en base pour le meme `Project`. Une dependance absente ou ambigue est
bloquante.

L'ordre `metadata -> medias/images -> web-pages -> accommodations` reste l'ordre attendu
pour la V1, mais il doit etre considere comme le resultat du graphe de dependances, pas
comme une suite d'appels rigide codee dans la commande. Cette approche permet d'ajouter plus
tard d'autres familles (`Article`, `Menu`, `Redirect`, `Offer`, etc.) sans reecrire
l'orchestration centrale.

## Hors perimetre V1

- Interface graphique d'import.
- Synchronisation bidirectionnelle entre API et fichiers source.
- Import incremental complexe avec detection fine de suppression.
- Import de donnees non rattachees a un projet sans contrat explicite.
- Creation automatique des organisations, projets ou memberships depuis le dossier de seed.
- Ajout de dependances lourdes si le parseur standard suffit.
- Import des blocs de contenu imbriques dans le body Markdown des pages, sauf si un contrat
  backend/front explicite est formalise dans une tache suivante.

## Plan d'execution

- [x] Choisir le nom final de la variable `.env` qui porte le chemin racine d'import.
- [x] Supprimer l'enregistrement Symfony de `app:fixtures:load-demo`.
- [x] Creer `app:seed:load-project` avec un argument `{projectSlug}`.
- [x] Resoudre le `Project` existant par slug et echouer explicitement s'il est absent.
- [x] Definir et documenter la structure de dossiers attendue sous
      `{SEED_PROJECT_PATH}/{projectSlug}`.
- [x] Remplacer les donnees codees en dur par une lecture de fichiers depuis le chemin
      configure et le slug projet.
- [x] Ajouter une etape preflight qui parse et valide toutes les sources du projet avant la
      premiere ecriture Doctrine.
- [x] Ajouter un rapport d'analyse avant import : volumes detectes, fichiers ignores,
      references resolues, erreurs bloquantes et avertissements.
- [x] Ajouter une confirmation interactive avant toute ecriture Doctrine.
- [x] Ajouter une selection interactive des familles d'entites a importer :
      metadata, media, web-pages, accommodations ou tout importer.
- [x] Ajouter une option explicite pour les executions non interactives confirmees, par
      exemple `--force`, qui reste interdite si le preflight echoue.
- [x] Ajouter une option non interactive de selection, par exemple `--only=metadata,media`.
- [x] Valider les dependances de selection avant import et refuser une selection incoherente.
- [x] Separer clairement le chargement des metadata, medias, pages et accommodations.
- [x] Introduire des importers specialises par famille d'entites, avec dependances
      declarees.
- [x] Construire un plan d'import depuis les dependances declarees et la selection demandee.
- [x] Garantir que le plan V1 aboutit a l'ordre :
      metadata -> medias/images -> web-pages -> accommodations.
- [x] Garantir que `project_id` vient du `Project` resolu en base par `{projectSlug}`, jamais
      d'un payload fichier.
- [x] Valider que chaque reference `WebPage.associatedMedia` existe dans les medias charges.
- [x] Valider que chaque reference `Accommodation.amenityFeature` existe comme
      `CategoryCode` avec `inCodeSet = amenity-feature`.
- [x] Valider que chaque reference `Accommodation.category` existe comme `CategoryCode` avec
      `inCodeSet = accommodation-type`.
- [x] Valider que chaque reference `Accommodation.place` existe comme `CategoryCode` avec
      `inCodeSet = accommodation-place`.
- [x] Valider que chaque reference `Accommodation.realEstateListing` existe comme
      `CategoryCode` avec `inCodeSet = real-estate-listing`.
- [x] Decider si les alias media `media-{basename}` sont supportes temporairement ou si le
      seed doit etre corrige avant import.
- [x] Ajouter une validation d'entree lisible : chemin absent, fichier manquant, YAML invalide,
      projet inconnu, ressource rattachee au mauvais projet.
- [ ] Conserver ou remplacer l'option `--with-test-tenants` sans casser les tests
      fonctionnels existants.
- [ ] Decider comment les tests fonctionnels creent leur socle local apres suppression de
      `app:fixtures:load-demo` sans dependre de `/home/johan/www/graines-digitales/seed`.
- [x] Mettre a jour `docs/10-operations/bootstrap-manuel.md`.
- [x] Supprimer `docs/10-operations/demo-fixtures.md`, devenu obsolete avec la suppression
      de `app:fixtures:load-demo`.
- [ ] Adapter les tests fonctionnels qui dependent des anciennes fixtures demo.

## Validations attendues

- [x] `docker compose exec -T api php -l src/Command/LoadProjectSeedCommand.php`
- [x] `docker compose exec -T api php bin/console lint:container`
- [x] `docker compose exec -T api php bin/console doctrine:schema:validate`
- [ ] `docker compose exec -T api php bin/console app:seed:load-project mlk-modern-site-web`
- [ ] `docker compose exec -T api php bin/console app:seed:load-project blue-bay-mogador`
- [x] `docker compose exec -T api php bin/console app:fixtures:load-demo` n'existe plus.
- [x] Relancer la commande deux fois ne duplique pas les donnees pour le perimetre valide
      `metadata,media`.
- [ ] Les ressources importees pour un projet ne sont pas visibles depuis un autre projet.
- [ ] Une reference media de page absente produit une erreur explicite avec le fichier source.
- [ ] Une reference amenity absente ou dans le mauvais `inCodeSet` produit une erreur explicite
      avec le fichier source.
- [ ] En mode interactif, la commande affiche le rapport d'analyse et n'ecrit rien tant que
      l'utilisateur n'a pas confirme.
- [ ] En mode interactif, la commande permet de choisir les familles d'entites a importer
      et refuse une selection dont les dependances sont absentes.
- [ ] En mode non interactif sans option de confirmation explicite, la commande refuse
      l'import apres le preflight.
- [ ] En mode non interactif, `--only` limite l'import aux familles demandees sans
      contourner les validations de dependances.
- [ ] Le plan d'import est calcule depuis les dependances declarees par les importers, et non
      depuis une sequence rigide codee directement dans la commande.
- [ ] Les tests fonctionnels critiques d'isolation inter-projets restent verts.

## Etat de validation du seed Blue Bay

Validation locale du 2026-05-27 :

- `metadata,media` s'importe correctement pour `blue-bay-mogador`.
- Les images seed sont copiees dans `WEBAPPS_DIR/{project.mediaPath}` et dans
  `WEBAPPS_DIR/{project.mediaPath}/originals`.
- Une seconde execution de `metadata,media` ne duplique pas les `CategoryCode` et
  `MediaObject`.
- L'import complet reste bloque par le preflight, comme attendu, car le seed contient encore
  des references non resolues :
  - plusieurs `WebPage.associatedMedia` pointent vers des fichiers absents du dossier
    `resources/images` et de `media-object.yaml` ;
  - plusieurs `Accommodation.amenityFeature` pointent vers des valeurs absentes de
    `CategoryCode` avec `inCodeSet = amenity-feature` ;
  - quelques `Accommodation.associatedMedia` pointent encore vers des UUID historiques non
    resolvables par la V1.

Note : tant que les ecarts du seed identifies ci-dessus ne sont pas corriges ou traites par
une regle explicite, les commandes `app:seed:load-project` doivent echouer proprement en
preflight plutot que realiser un import partiel.

## Points de vigilance

- Securite : aucun `project_id` ne doit etre accepte comme source de verite depuis un fichier
  de ressource sans validation du contexte projet.
- Donnees : les imports doivent etre idempotents avec des cles fonctionnelles stables
  (`slug`, `identifier`, `email`, `code`, `locale`).
- Medias : verifier les chemins physiques, les URLs publiques et les checksums sans supposer
  que les fichiers existent en production.
- Tests : les tests fonctionnels ne doivent pas devenir dependants de donnees locales
  absentes en CI.
- DevEx : les erreurs de format doivent indiquer le fichier et le champ en cause.
- KISS : garder une V1 fichier -> entites Doctrine directe avant d'ajouter un moteur
  d'import plus abstrait.
- Alignement front/back : toute donnee ignoree parce qu'elle n'est pas encore modelisee doit
  etre listee comme ecart de contrat, pas absorbee silencieusement dans un champ generique.
