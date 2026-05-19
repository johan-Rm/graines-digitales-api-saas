# Jobs asynchrones

Messenger porte les traitements asynchrones.

Job actuel :

- `AnalyzeMediaObject`

Regles :

- ne pas executer de traitement long dans la requete HTTP
- rendre les handlers idempotents quand c'est possible
- documenter les effets de bord d'un job
