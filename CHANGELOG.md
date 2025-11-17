# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

