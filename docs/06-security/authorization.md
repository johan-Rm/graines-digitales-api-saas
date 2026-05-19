# Autorisation

L'autorisation projet passe par `ProjectAccessGuard`.

Regles :

- un utilisateur doit etre membre du projet pour lire
- `owner`, `admin` et `editor` peuvent ecrire
- `viewer` ne peut pas ecrire
- une ressource d'un autre projet doit etre masquee ou refusee cote serveur
