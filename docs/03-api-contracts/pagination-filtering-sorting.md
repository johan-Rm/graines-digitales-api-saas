# Pagination, filtrage et tri

API Platform porte la pagination et les filtres standards.

Regles projet :

- Les filtres ne remplacent jamais l'isolation serveur par projet.
- Les collections projet excluent les ressources archivees.
- Les tris et filtres doivent etre indexes si leur usage devient critique.
