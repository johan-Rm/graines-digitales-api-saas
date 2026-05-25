# Schema base de donnees

- PostgreSQL est la base de reference.
- Doctrine ORM porte le mapping.
- Les tables projet ont un `project_id` indexe.
- Les memberships organisation sont uniques par couple `(organization_id, user_id)`.
- Les memberships projet sont uniques par couple `(project_id, user_id)`.
- Les slugs, locales et checksums ne sont indexes que si un cas de lecture ou
  une contrainte d'unicite le justifie.
- Les biens immobiliers sont portes par `accommodation` et
  `accommodation_translation`.
- Les referentiels immobiliers utilisent `category_code.in_code_set` pour
  distinguer type de bien, type d'annonce, lieu, equipements et tags.
- Les medias associes aux biens passent par `accommodation_media`, qui porte
  l'ordre, la legende et les mots-cles.
- Les qualites d'un bien passent par `property_value`, rattachee a
  `accommodation`, avec `name`, `value` et `position`. `name` est borne par
  l'enum applicatif `AccommodationQuality`.
