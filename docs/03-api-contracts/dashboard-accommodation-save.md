# Sauvegarde dashboard Accommodation

Deux routes complementaires couvrent la sauvegarde d'un bien immobilier.

## Sauvegarde mono-locale (champs globaux + une locale)

```txt
PUT /api/projects/{projectId}/accommodations/{id}?locale={locale}
PUT /api/projects/{projectId}/accommodations/{identifier}?locale={locale}
```

Le dashboard envoie les champs globaux et les champs localises pour une seule locale.
La locale cible est celle du parametre `locale` (ou `Accept-Language`).
La route UUID n'accepte pas de tableau `translations`. La variante par `identifier`
existe pour les ecrans qui ne connaissent pas encore l'UUID technique du bien.

```json
{
  "isActive": true,
  "offer": {
    "price": "660000",
    "priceCurrency": "MAD",
    "priceSpecification": "net vendeur",
    "availability": "InStock"
  },
  "areaSize": "27",
  "floorSize": "27",
  "name": "Superbe local commercial a Erraounak",
  "body": "<p>Local commercial renove.</p>",
  "metaTitle": "Local commercial a vendre a Essaouira",
  "metaDescription": "Local commercial renove a Erraounak."
}
```

La reponse renvoie les champs de la locale resolue (aplatis a la racine).

## Sauvegarde multilingue (toutes les locales)

```txt
PUT /api/projects/{projectId}/accommodations/{id}/translations
PUT /api/projects/{projectId}/accommodations/{identifier}/translations
```

Le dashboard peut envoyer plusieurs traductions en une seule requete. Cette route accepte
un tableau `translations` et peut aussi recevoir les champs globaux de l'accommodation
quand l'ecran multilingue les expose dans le meme formulaire.

```json
{
  "translations": [
    {
      "locale": "fr",
      "name": "Superbe local commercial a Erraounak",
      "body": "<p>Texte source.</p>"
    },
    {
      "locale": "en",
      "name": "Custom English title",
      "body": "<p>English body.</p>"
    },
    {
      "locale": "es",
      "name": "Local comercial en Erraounak",
      "body": "<p>Texto en espanol.</p>"
    }
  ]
}
```

Les traductions fournies sont creees ou mises a jour. Les traductions existantes non
incluses dans le payload ne sont pas supprimees ; elles peuvent etre conservees ou
completees par le pipeline de traduction automatique. La reponse embarque la collection
`translations` complete aux cotes des champs racine non localises.

Regles de sauvegarde :

- Une valeur non vide fournie par le dashboard est consideree comme personnalisee et ne
  doit pas etre ecrasee par la traduction automatique.
- Une valeur absente, `null` ou vide peut etre completee automatiquement depuis la locale
  source.
- Les champs globaux de l'`Accommodation` restent communs a toutes les locales et ne
  doivent pas etre dupliques dans chaque objet de traduction.
- Le prix et la disponibilite sont portes par l'objet global `offer`.
- Les surfaces `floorSize`, `areaSize`, `areaTerrace` et `landArea` sont exposees et
  sauvegardees comme des chaines pour conserver les valeurs composees ou formatees.

Champs localises geres :

- `slug`
- `name`
- `label`
- `highlight`
- `body`
- `review`
- `metaTitle`
- `metaDescription`

## Traduction automatique

L'API utilise un client Google Translate cote backend. Tant que la configuration
definitive n'est pas disponible, le client peut fonctionner en mode fictif et retourner
la chaine source. Ce comportement permet de valider le contrat de sauvegarde sans
bloquer le dashboard sur les credentials externes.
