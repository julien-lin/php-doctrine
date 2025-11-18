# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.9] - 2024-11-18

### Corrigé
- **MigrationGenerator** : Correction de la génération SQL pour les colonnes ID avec `AUTO_INCREMENT`
  - Les colonnes ID incluent maintenant `AUTO_INCREMENT` dans les migrations `ALTER TABLE`
  - Détection correcte de la colonne primaire dans `generateAlterTableSQL()`

## [1.0.8] - 2024-11-17

### Corrigé
- **MigrationGenerator** : Amélioration de la détection des changements de colonnes
  - Utilisation de `COLUMN_TYPE` au lieu de `DATA_TYPE` pour une comparaison précise des types SQL
  - Ajout de la comparaison des valeurs par défaut (`COLUMN_DEFAULT`)
  - Ajout de la comparaison d'`AUTO_INCREMENT` pour les colonnes ID
  - Normalisation des types SQL équivalents (`TINYINT(1)` = `BOOLEAN`, `INTEGER` = `INT`)
  - Amélioration de la gestion des erreurs dans `getExistingColumns()` (exceptions au lieu de tableaux vides)
- **MigrationGenerator** : Ajout de méthodes de normalisation
  - `normalizeSQLTypeForComparison()` : Normalise les types SQL équivalents pour comparaison
  - `normalizeDefaultValue()` : Normalise les valeurs par défaut pour comparaison

### Amélioration
- Détection plus précise des migrations nécessaires, notamment pour les colonnes booléennes (`TINYINT(1)`)
- Meilleure gestion des erreurs lors de la récupération des métadonnées de colonnes

## [1.0.6] - 2024-11-XX

### Ajouté
- Système de migrations complet avec génération automatique de SQL
- Script CLI `bin/doctrine-migrate` pour gérer les migrations
- MigrationManager pour suivre l'historique des migrations
- MigrationRunner pour exécuter les migrations de manière sécurisée
- MigrationGenerator pour générer les migrations depuis les entités
- Cache des instances ReflectionClass pour améliorer les performances
- Validation des identifiants SQL pour prévenir les injections SQL
- Méthode `escapeStringValue()` pour un échappement SQL plus sûr

### Modifié
- Amélioration de la sécurité dans `EntityRepository::findBy()` avec validation des noms de champs
- Amélioration de l'échappement SQL dans `MigrationGenerator` (utilisation de PDO::quote)
- Traduction de la description du package en anglais pour Packagist
- Ajout de la section `config` dans composer.json

### Sécurité
- Correction d'une vulnérabilité potentielle d'injection SQL dans `findBy()` et `orderBy`
- Validation stricte des identifiants SQL (noms de colonnes, tables)
- Validation des directions de tri (ASC/DESC uniquement)

### Performance
- Cache des instances ReflectionClass pour éviter les créations répétées
- Réutilisation des instances MetadataReader

## [1.0.0] - 2024-XX-XX

### Ajouté
- Entity Manager pour gérer le cycle de vie des entités
- Repository Pattern avec EntityRepository
- Query Builder pour construire des requêtes SQL de manière fluide
- Mapping avec Attributes PHP 8 (Entity, Column, Id, Relations)
- Support des relations OneToMany, ManyToOne, ManyToMany
- Gestion des transactions
- Support multi-SGBD (MySQL, PostgreSQL, SQLite)
- MetadataReader avec cache des métadonnées

[1.0.6]: https://github.com/julien-lin/doctrine-php/compare/v1.0.0...v1.0.6
[1.0.0]: https://github.com/julien-lin/doctrine-php/releases/tag/v1.0.0

