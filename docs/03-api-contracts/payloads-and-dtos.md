# Payloads et DTOs

- Les champs d'audit sont en lecture seule.
- Les payloads projet ne doivent pas accepter `project` comme source de verite.
- Les DTOs dedies doivent distinguer lecture publique, ecriture backoffice et
  payloads techniques.
- Les lectures `Accommodation` exposent les champs localises directement au niveau racine
  pour la locale resolue et n'exposent pas la collection technique `translations`.
- Les relations directes dans une lecture `Accommodation` sont referencees par UUID. Les
  objets de jointure utiles, comme `associatedMedia`, peuvent conserver leurs champs propres
  mais doivent referencer leurs ressources liees par UUID.

TASK-002 prevoit des DTOs explicites pour les contenus localises :

- lecture localisee
- gestion des traductions
- statut de completude par locale
