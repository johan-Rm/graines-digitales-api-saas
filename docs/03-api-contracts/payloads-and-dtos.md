# Payloads et DTOs

- Les champs d'audit sont en lecture seule.
- Les payloads projet ne doivent pas accepter `project` comme source de verite.
- Les DTOs dedies doivent distinguer lecture publique, ecriture backoffice et
  payloads techniques.

TASK-002 prevoit des DTOs explicites pour les contenus localises :

- lecture localisee
- gestion des traductions
- statut de completude par locale
