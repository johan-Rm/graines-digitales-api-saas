# Task : Correction du champ `level` dans l'entité Accommodation

## Metadonnees

- ID : TASK-015
- Source : audit source de vérité schemas/ — 2026-05-24
- Statut : Fait
- Périmètre : `src/Entity/Accommodation.php`, migration Doctrine, fixtures
- Dépendances fonctionnelles : aucune

## Objectif

Le champ `level` de l'entité `Accommodation` est actuellement typé `?bool` en PHP et
mappé en colonne `boolean` en base.

Le contrat défini dans `schemas/webapp/accommodation.yaml` (source de vérité partagée)
le déclare comme `number` : il représente un entier (nombre d'étages ou numéro de niveau),
pas une valeur binaire plain-pied/pas plain-pied.

Cette tâche aligne l'entité PHP sur le contrat YAML.

## Changements attendus

### `src/Entity/Accommodation.php`

```php
// avant
#[ORM\Column(type: 'boolean', nullable: true)]
private ?bool $level = null;

public function getLevel(): ?bool { return $this->level; }
public function setLevel(?bool $level): self { $this->level = $level; return $this; }

// après
#[ORM\Column(type: 'smallint', nullable: true)]
private ?int $level = null;

public function getLevel(): ?int { return $this->level; }
public function setLevel(?int $level): self { $this->level = $level; return $this; }
```

### Migration Doctrine

Générer une migration qui :
- convertit la colonne `level` de `boolean` → `smallint`
- migre les données existantes : `true` → `1`, `false` → `0` ou `NULL`

```bash
docker compose exec api php bin/console doctrine:migrations:diff
# réviser le fichier généré avant d'appliquer
docker compose exec api php bin/console doctrine:migrations:migrate
```

La migration doit être réversible (`down()` reconvertit `smallint` → `boolean`).

### Fixtures

Mettre à jour les fixtures concernées si elles initialisent `level` avec `true`/`false`.

## Validation

```bash
docker compose exec api php bin/console lint:container
docker compose exec api php bin/console doctrine:schema:validate
docker compose exec api php bin/phpunit
```

- Le schéma BDD est valide
- Les tests fonctionnels passent
- La migration est réversible

## Points de vigilance

- Vérifier les groupes de sérialisation API Platform : si `level` est exposé dans une
  réponse JSON, s'assurer que la valeur serialisée reste cohérente (int, pas bool).
- Côté mlk, le `accommodationMapper.ts` ne fait pas de cast sur `level` — à vérifier
  qu'aucune valeur `1`/`0` n'est interprétée comme truthy/falsy dans le rendu.
- Mettre à jour `schemas/dtos/symfony_api/accommodation.yaml` après cette tâche si ce
  n'est pas déjà fait (TASK-DGDOC-001).
