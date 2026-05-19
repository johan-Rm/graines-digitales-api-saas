# Documentation API

Ce dossier est le point d'entree de la documentation technique de l'API
Symfony 8 / API Platform.

## Ordre de lecture

1. [`01-onboarding/overview.md`](01-onboarding/overview.md)
2. [`02-system-design/index.md`](02-system-design/index.md)
3. [`03-api-contracts/index.md`](03-api-contracts/index.md)
4. [`04-domain-model/index.md`](04-domain-model/index.md)
5. [`05-data-layer/index.md`](05-data-layer/index.md)
6. [`06-security/index.md`](06-security/index.md)
7. [`07-integrations/index.md`](07-integrations/index.md)
8. [`08-observability/index.md`](08-observability/index.md)
9. [`09-quality/index.md`](09-quality/index.md)
10. [`10-operations/index.md`](10-operations/index.md)

## Regle de propriete documentaire

Chaque regle doit avoir un seul proprietaire documentaire. Une page peut pointer
vers une autre page, mais ne doit pas recopier sa regle.

| Sujet | Proprietaire |
| --- | --- |
| Installation locale, creation user, token JWT, premiers appels API | `01-onboarding/overview.md` |
| Runtime, cycle de requete, frontieres, erreurs techniques, dependances | `02-system-design/` |
| Routes, payloads, DTO, versioning, pagination, OpenAPI | `03-api-contracts/` |
| Entites, invariants metier, use cases | `04-domain-model/` |
| Schema SQL, migrations, repositories, transactions | `05-data-layer/` |
| Authentification, autorisation, validation, secrets | `06-security/` |
| Services externes, webhooks, jobs asynchrones | `07-integrations/` |
| Logs, metriques, traces, alertes | `08-observability/` |
| Strategie de test, CI, checklist de review | `09-quality/` |
| Environnements, deploiement, rollback, runbooks | `10-operations/` |

## Sources de contexte

- `README.md` donne le resume projet et les commandes courantes.
- `dev-book/tasks/` formalise les travaux planifies ou en cours.
- Le code fait autorite quand une documentation est absente ou obsolete.
