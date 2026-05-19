# Gestion des erreurs techniques

- Une ressource hors projet doit en general etre masquee en `404`.
- Une action authentifiee sans droit suffisant retourne `403`.
- Une requete non authentifiee retourne `401`.
- Les payloads invalides suivent le format d'erreur API Platform.

Les messages d'erreur ne doivent pas reveler l'existence d'une ressource d'un
autre projet.
