# Cycle de requete

Les routes projet suivent le cycle standard suivant :

1. Le firewall valide le JWT.
2. API Platform resout l'operation.
3. Le provider ou processor recupere `{projectId}`.
4. `ProjectAccessGuard` verifie le membership courant.
5. Doctrine charge ou persiste la ressource.
6. Le serializer expose la reponse selon les groupes configures.

Les routes `/api/projects/{projectId}/...` ne doivent jamais faire confiance a
un `project` transmis dans le payload.
