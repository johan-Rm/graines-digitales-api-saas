# Repositories

Les repositories peuvent encapsuler les requetes complexes, mais ne doivent pas
court-circuiter les regles d'isolation projet.

Pour les routes `/api/projects/{projectId}/...`, la resolution projet reste la
responsabilite des providers/processors et de `ProjectAccessGuard`.
