# Logging

Les logs Symfony standards sont disponibles par environnement.

Regles :

- pas de `console.log` ou dump en production
- pas de secret dans les logs
- les erreurs de securite ne doivent pas reveler les ressources hors projet
