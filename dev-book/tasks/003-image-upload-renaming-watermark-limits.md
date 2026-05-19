# Task : Upload image, renommage, filigrane et limites

## Metadonnees

- ID : TASK-003
- Source : ajustement utilisateur du 2026-05-19
- Statut : A faire
- Perimetre : API Symfony 8 / media objects / upload image
- Dependance fonctionnelle : TASK-001

## Objectif

Renforcer l'upload image MVP avec un pipeline fiable : validation stricte, limitation des
uploads, renommage controle des fichiers, ajout optionnel d'un filigrane et persistance des
metadonnees utiles pour les webapps clientes.

## Perimetre MVP

- Upload image via `POST multipart/form-data`.
- Limitation par MIME type, extension, taille et poids total accepte.
- Renommage serveur des fichiers, sans faire confiance au nom fourni par le client.
- Conservation de `originalFilename`.
- Calcul et stockage de `checksumSha256`.
- Ajout d'un filigrane configurable par projet ou par environnement.
- Stockage local dev dans `public/media`.
- URL publique exploitable par les clients.

## Contraintes

- Ne jamais executer ou servir un fichier non valide comme image.
- Ne pas exposer le nom original comme nom physique.
- Garder le stockage remplacable plus tard par S3, MinIO ou CDN.
- Le filigrane ne doit pas degrader les performances des endpoints critiques.
- Les traitements longs doivent etre prepares pour Messenger si necessaire.

## Plan d'execution

- [ ] Definir les limites upload dans `.env.example`.
- [ ] Ajouter une configuration projet/environnement pour le filigrane.
- [ ] Implementer un service de renommage deterministe et lisible.
- [ ] Implementer la validation image stricte.
- [ ] Implementer ou preparer le service de filigrane.
- [ ] Brancher le pipeline dans la creation `MediaObject`.
- [ ] Ajouter les champs de statut necessaires.
- [ ] Ajouter les tests d'upload valide.
- [ ] Ajouter les tests de refus fichier non image.
- [ ] Ajouter les tests de taille maximale.
- [ ] Ajouter les tests de renommage et checksum stable.

## Validations attendues

- [ ] Un fichier image valide cree un `MediaObject`.
- [ ] Un fichier non image est refuse.
- [ ] Un fichier trop lourd est refuse.
- [ ] Le fichier physique est renomme cote serveur.
- [ ] Le nom original reste auditable.
- [ ] Le checksum est renseigne.
- [ ] Le filigrane est applique ou explicitement desactive selon config.
