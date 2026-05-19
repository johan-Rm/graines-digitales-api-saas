# Frontieres de modules

- `src/Entity/` porte l'etat persiste et les invariants locaux.
- `src/State/` porte les providers et processors API Platform.
- `src/Security/` porte l'utilisateur courant et les regles d'acces projet.
- `src/Message/` et `src/MessageHandler/` portent les traitements asynchrones.
- `src/Encoder/` porte le decodage multipart et uploaded files.
- `src/Serializer/` porte les ajustements de normalisation.

Les controllers ne sont ajoutes que lorsque les operations API Platform ne
suffisent pas.
