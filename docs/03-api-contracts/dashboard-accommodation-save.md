# Sauvegarde dashboard Accommodation

Le dashboard sauvegarde un bien immobilier existant par son identifiant metier :

```txt
PUT /api/projects/{projectId}/accommodations/{identifier}?locale={locale}
```

L'`identifier` est commun a toutes les locales. La route accepte deux usages.

## Sauvegarde mono-locale

Le dashboard envoie les champs localises directement a la racine du payload. La locale
mise a jour est celle du parametre `locale`.

```json
{
  "name": "Superbe local commercial a Erraounak",
  "body": "<p>Local commercial renove.</p>",
  "offer": {
    "price": "660000",
    "priceCurrency": "MAD",
    "priceSpecification": "net vendeur",
    "availability": "InStock"
  },
  "areaSize": "27",
  "areaTerrace": "12",
  "floorSize": "27",
  "landArea": "550/580",
  "metaTitle": "Local commercial a vendre a Essaouira",
  "metaDescription": "Local commercial renove a Erraounak."
}
```

Si seules les valeurs de la locale source sont envoyees, l'API peut creer les
traductions manquantes pour les autres locales activees du projet.

## Sauvegarde multilingue

Le dashboard peut fournir plusieurs traductions dans `translations`.

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
      "body": ""
    },
    {
      "locale": "es",
      "name": "",
      "body": ""
    }
  ]
}
```

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
- Pour limiter les effets de bord, le dashboard doit envoyer uniquement les champs que
  l'utilisateur vient de modifier, plus les traductions a completer.

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
