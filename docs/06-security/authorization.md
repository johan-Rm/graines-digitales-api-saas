# Autorisation

L'autorisation projet passe par `ProjectAccessGuard`.

Regles :

- la creation d'une organisation est reservee au `ROLE_SUPER_ADMIN`
- `ROLE_SUPER_ADMIN` est resolu depuis l'environnement, pas depuis un payload public
- un utilisateur peut appartenir a une organisation via `OrganizationMember`
- `OrganizationMember(owner|admin)` peut creer des projets dans son organisation
- un utilisateur doit etre membre du projet pour lire
- `owner`, `admin` et `editor` peuvent ecrire
- `viewer` ne peut pas ecrire
- seuls `ProjectMember(owner|admin)`, `OrganizationMember(owner|admin)` de
  l'organisation du projet ou un super admin peuvent gerer les membres projet
- un utilisateur doit deja etre `OrganizationMember` de l'organisation proprietaire
  avant de pouvoir devenir `ProjectMember`
- une ressource d'un autre projet doit etre masquee ou refusee cote serveur
