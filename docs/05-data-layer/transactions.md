# Transactions

Doctrine gere les transactions standards de persistance.

Une transaction explicite est justifiee si plusieurs ecritures doivent reussir
ou echouer ensemble, par exemple :

- creation coordonnee de fixtures
- migration de donnees
- orchestration metier multi-entites

Les traitements longs ne doivent pas garder une transaction ouverte inutilement.
