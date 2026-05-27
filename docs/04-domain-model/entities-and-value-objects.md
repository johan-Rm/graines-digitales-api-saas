# Entites et objets valeur

Entites principales actuelles :

- `Organization`
- `OrganizationMember`
- `Project`
- `ProjectMember`
- `User`
- `MediaObject`
- `Accommodation`
- `AccommodationMedia`
- `PropertyValue`
- `WebPage`
- `Article`
- `CategoryCode`
- `Message`

Les identifiants domaine sont des UUID.

`Organization` porte l'identite du tenant et ses champs publics de profil :
`acronym`, `fullName`, `alternateName`, `location`, `image`, `email`,
`phoneNumbers` et `description`.

`PropertyValue.name` utilise le referentiel ferme `AccommodationQuality` :
`Confort`, `Architecture`, `Intérieur`.
