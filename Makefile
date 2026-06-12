SHELL := /bin/sh

DOCKER_COMPOSE ?= docker compose
COMPOSE_DEV ?= $(DOCKER_COMPOSE) -f docker-compose.dev.yml
API_SERVICE ?= api
POSTGRES_SERVICE ?= postgres

EXEC = $(COMPOSE_DEV) exec $(API_SERVICE)
EXEC_T = $(COMPOSE_DEV) exec -T $(API_SERVICE)

.DEFAULT_GOAL := help

-include Makefile.common
-include Makefile.dev
-include Makefile.preprod
-include Makefile.prod

.PHONY: help

help:
	@printf "\n"
	@printf "═══════════════════════════════════════════════════════════════\n"
	@printf " 🧩 API SYMFONY - COMMANDES DISPONIBLES\n"
	@printf "═══════════════════════════════════════════════════════════════\n"
	@printf "\n"

	@printf "🌐 Cibles globales (env-agnostiques)\n\n"
	@printf "  test                        Suite PHPUnit complète\n"
	@printf "  test-file FILE=\"...\"        Exécuter un fichier de test\n"
	@printf "  test-filter FILTER=\"...\"    Exécuter des tests filtrés\n"
	@printf "  test-debug                  PHPUnit mode debug\n"
	@printf "  schema-validate             Valider le schéma Doctrine\n"
	@printf "  lint-container              Valider le container Symfony\n"
	@printf "\n"

	@printf "✅ Qualité\n\n"
	@printf "  quality-fast                Contrôles rapides\n"
	@printf "  quality                     Contrôles complets\n"
	@printf "  analyse                     PHPStan\n"
	@printf "  cs-check                    PHP-CS-Fixer check\n"
	@printf "  cs-fix                      PHP-CS-Fixer fix\n"
	@printf "  deptrac                     Vérification architecture\n"
	@printf "  rector-check                Prévisualisation Rector\n"
	@printf "  rector-fix                  Application Rector\n"
	@printf "  infection                   Mutation testing\n"
	@printf "  audit                       Audit sécurité\n"
	@printf "  diff-check                  Vérification du diff Git\n"
	@printf "\n"

	@printf "═══════════════════════════════════════════════════════════════\n"
	@printf "\n"

	@printf "📦 DEV\n\n"
	@printf "  dev-up                      Démarrer les services dev\n"
	@printf "  dev-up-build                Démarrer les services dev avec rebuild\n"
	@printf "  dev-down                    Arrêter et supprimer les containers dev\n"
	@printf "  dev-stop                    Stopper les containers dev\n"
	@printf "  dev-restart                 Redémarrer les services dev\n"
	@printf "  dev-clean                   Reset complet dev avec suppression des volumes\n"
	@printf "  dev-status                  Afficher l'état des services dev\n"
	@printf "\n"

	@printf "🧱 Symfony & Base de données (dev)\n\n"
	@printf "  dev-console CMD=\"...\"       Console Symfony en dev\n"
	@printf "  dev-composer CMD=\"...\"      Commande Composer en dev\n"
	@printf "  dev-install                 Installer les dépendances Composer\n"
	@printf "  dev-update-lock             Rafraîchir composer.lock\n"
	@printf "  dev-cache-clear             Vider le cache Symfony\n"
	@printf "  dev-jwt                     Générer le keypair JWT\n"
	@printf "  dev-migrate                 Jouer les migrations Doctrine\n"
	@printf "\n"

	@printf "📜 Logs & Shell\n\n"
	@printf "  dev-logs                    Suivre les logs dev\n"
	@printf "  dev-logs-api                Suivre les logs du container API\n"
	@printf "  dev-shell                   Ouvrir un shell dans le container API\n"
	@printf "\n"

	@printf "⚙️ Bootstrap & Données\n\n"
	@printf "  dev-setup                   Setup local : up + install + JWT + migrations\n"
	@printf "  dev-deploy                  Setup complet + bootstrap + healthcheck\n"
	@printf "  dev-reset-cache             Supprimer var/cache Symfony\n"
	@printf "  dev-bootstrap-manual        Lancer le bootstrap applicatif manuel\n"
	@printf "  dev-fixtures-test           Charger les fixtures de test\n"
	@printf "  dev-seed-load               Charger les seeds d'un projet\n"
	@printf "  dev-create-super-admin      Créer ou mettre à jour un super admin\n"
	@printf "  dev-create-organization     Créer une organisation\n"
	@printf "  dev-db-test-create          Créer la base de test\n"
	@printf "\n"

	@printf "═══════════════════════════════════════════════════════════════\n"
	@printf "\n"

	@printf "🚧 PREPROD\n\n"
	@printf "  preprod-up                  Lancer les services preprod\n"
	@printf "  preprod-up-build            Lancer la preprod avec rebuild\n"
	@printf "  preprod-build               Builder les images preprod\n"
	@printf "  preprod-build-no-cache      Builder la preprod sans cache\n"
	@printf "  preprod-deploy              Déploiement preprod complet\n"
	@printf "\n"

	@printf "📜 Logs & Shell\n\n"
	@printf "  preprod-logs                Suivre les logs preprod\n"
	@printf "  preprod-shell               Shell API preprod\n"
	@printf "  preprod-console CMD=\"...\"   Console Symfony preprod\n"
	@printf "\n"

	@printf "⚙️ Données & Maintenance\n\n"
	@printf "  preprod-jwt                 Générer le keypair JWT preprod\n"
	@printf "  preprod-migrate             Jouer les migrations preprod\n"
	@printf "  preprod-seed-load           Charger les seeds preprod\n"
	@printf "  preprod-healthcheck         Vérifier que l'API preprod répond\n"
	@printf "\n"

	@printf "🧹 Lifecycle\n\n"
	@printf "  preprod-down                Arrêter et supprimer les containers preprod\n"
	@printf "  preprod-stop                Stopper les containers preprod\n"
	@printf "  preprod-clean               Reset volumes preprod\n"
	@printf "  preprod-status              Afficher l'état des services preprod\n"
	@printf "\n"

	@printf "═══════════════════════════════════════════════════════════════\n"
	@printf "\n"

	@printf "🏭 PROD\n\n"
	@printf "  prod-up                     Lancer les services prod\n"
	@printf "  prod-up-build               Lancer la prod avec rebuild\n"
	@printf "  prod-build                  Builder les images prod\n"
	@printf "  prod-build-no-cache         Builder la prod sans cache\n"
	@printf "  prod-deploy                 Déploiement prod complet\n"
	@printf "\n"

	@printf "📜 Logs & Shell\n\n"
	@printf "  prod-logs                   Suivre les logs prod\n"
	@printf "  prod-shell                  Shell API prod\n"
	@printf "  prod-console CMD=\"...\"      Console Symfony prod\n"
	@printf "\n"

	@printf "⚙️ Données & Maintenance\n\n"
	@printf "  prod-jwt                    Générer le keypair JWT prod\n"
	@printf "  prod-migrate                Jouer les migrations prod\n"
	@printf "  prod-seed-load              Charger les seeds prod\n"
	@printf "  prod-healthcheck            Vérifier que l'API prod répond\n"
	@printf "\n"

	@printf "🧹 Lifecycle\n\n"
	@printf "  prod-down                   Arrêter et supprimer les containers prod\n"
	@printf "  prod-stop                   Stopper les containers prod\n"
	@printf "  prod-clean                  Reset volumes prod\n"
	@printf "  prod-status                 Afficher l'état des services prod\n"
	@printf "\n"

	@printf "═══════════════════════════════════════════════════════════════\n"
	@printf "\n"