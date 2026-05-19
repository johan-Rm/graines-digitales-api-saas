# Regles de dependances

- Ne pas ajouter de dependance sans benefice clair pour le produit ou la
  maintenance.
- Preferer une abstraction locale simple si une dependance ajoute trop de
  surface.
- Les dependances de securite ou persistance doivent rester remplacables quand
  le MVP n'a pas encore stabilise le besoin.

Decisions actuelles :

- JWT : `lexik/jwt-authentication-bundle`.
- Refresh token : `gesdinet/jwt-refresh-token-bundle`.
- Upload : `vich/uploader-bundle`.
