# Schema base de donnees

- PostgreSQL est la base de reference.
- Doctrine ORM porte le mapping.
- Les tables projet ont un `project_id` indexe.
- Les memberships sont uniques par couple `(project_id, user_id)`.
- Les slugs, locales et checksums ne sont indexes que si un cas de lecture ou
  une contrainte d'unicite le justifie.
