# Regles metier

- Un projet appartient a une organisation.
- Une organisation est creee par un super admin plateforme.
- Un utilisateur appartient a une organisation via `OrganizationMember`.
- Une ressource projet appartient a un seul projet.
- Un utilisateur accede a un projet via `ProjectMember`.
- `OrganizationMember(owner|admin)` peut creer des projets dans son organisation.
- Un utilisateur doit deja appartenir a l'organisation avant d'etre ajoute a l'un de
  ses projets.
- `owner`, `admin` et `editor` peuvent ecrire.
- `viewer` peut lire uniquement.
- Les contenus archives sont exclus des collections standards.
- Le checksum d'un media est calcule cote serveur.

TASK-002 ajoute le modele de traduction des contenus.
