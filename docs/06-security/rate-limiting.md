# Rate limiting

Le firewall Symfony limite les tentatives de connexion sur `POST /api/auth/login`
a 5 echecs par fenetre de 15 minutes (login throttling, cle utilisateur/IP et
limite globale IP gerees par Symfony). Le handler Lexik conserve HTTP 401 et renvoie un message indiquant trop
de tentatives de connexion pour les requetes bloquees.

Le refresh token et les uploads n'ont pas encore de limite applicative dediee.
