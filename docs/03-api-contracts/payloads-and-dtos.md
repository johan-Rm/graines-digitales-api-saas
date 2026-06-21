# Payloads et DTOs

- Les champs d'audit sont en lecture seule.
- Les payloads projet ne doivent pas accepter `project` comme source de verite.
- Les DTOs dedies doivent distinguer lecture publique, ecriture backoffice et
  payloads techniques.
- Les lectures API exposent les champs localises directement au niveau racine pour la
  locale resolue et n'exposent pas la collection technique `translations`.
- Les relations directes dans les lectures API sont referencees par UUID. Les objets de
  jointure ou de valeur utiles, comme `associatedMedia` ou `PropertyValue`, peuvent
  conserver leurs champs propres mais doivent referencer leurs ressources liees par UUID.

## URLs des medias

`MediaObject.contentUrl` expose toujours une URI publique relative a l'origine du frontend :

```text
/images/{filePath}
```

L'API ne doit pas inclure de protocole ni de host dans ce champ. `Project.mediaPath` reste
le chemin physique de stockage et ne doit pas etre expose dans `contentUrl`.

TASK-002 prevoit des DTOs explicites pour les contenus localises :

- lecture localisee
- gestion des traductions
- statut de completude par locale
