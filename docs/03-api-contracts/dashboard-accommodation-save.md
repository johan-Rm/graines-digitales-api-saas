# Sauvegarde dashboard Accommodation

Deux routes complementaires couvrent la sauvegarde d'un bien immobilier.

## Sauvegarde mono-locale (champs globaux + une locale)

```txt
PUT /api/projects/{projectId}/accommodations/{id}?locale={locale}
```

Le dashboard envoie les champs globaux et les champs localises pour une seule locale.
La locale cible est celle du parametre `locale` (ou `Accept-Language`).
La route n'accepte pas de tableau `translations`.

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
```

Le dashboard peut remplacer l'ensemble des traductions en une seule requete.
Cette route accepte uniquement un tableau `translations` ; les champs globaux
de l'accommodation ne sont pas touches.

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

Les traductions existantes non incluses dans le payload sont supprimees (orphan removal).
La reponse embarque la collection `translations` complete aux cotes des champs racine.

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
- `locationDescription`

## Traduction automatique

L'API utilise un client Google Translate cote backend. Tant que la configuration
definitive n'est pas disponible, le client peut fonctionner en mode fictif et retourner
la chaine source. Ce comportement permet de valider le contrat de sauvegarde sans
bloquer le dashboard sur les credentials externes.
