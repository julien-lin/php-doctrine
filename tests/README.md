# Tests Unitaires - Doctrine PHP

Ce dossier contient les tests unitaires pour la librairie `doctrine-php`.

## Structure

```
tests/
├── Doctrine/
│   ├── ConnectionTest.php          # Tests pour Connection
│   ├── EntityManagerTest.php       # Tests pour EntityManager
│   ├── EntityRepositoryTest.php    # Tests pour EntityRepository
│   ├── MetadataReaderTest.php      # Tests pour MetadataReader
│   ├── QueryBuilderTest.php        # Tests pour QueryBuilder
│   └── MigrationGeneratorTest.php  # Tests pour MigrationGenerator
└── README.md
```

## Exécution des Tests

### Tous les tests
```bash
composer test
```

### Avec couverture de code
```bash
composer test-coverage
```

### Un fichier spécifique
```bash
vendor/bin/phpunit tests/Doctrine/EntityManagerTest.php
```

## Tests Disponibles

### ConnectionTest
- ✅ `testInTransaction()` : Vérifie la gestion des transactions
- ✅ `testExecute()` : Teste l'exécution de requêtes SQL
- ✅ `testFetchOne()` : Teste la récupération d'un seul résultat

### EntityManagerTest
- ✅ `testPersistAndFlush()` : Teste la persistance d'entités
- ✅ `testFind()` : Teste la recherche d'entités par ID
- ✅ `testDirtyChecking()` : Teste la détection des changements
- ✅ `testRemove()` : Teste la suppression d'entités

### EntityRepositoryTest
- ✅ `testFind()` : Teste la recherche par ID
- ✅ `testFindAll()` : Teste la récupération de toutes les entités
- ✅ `testFindBy()` : Teste la recherche par critères
- ✅ `testFindOneBy()` : Teste la recherche d'une entité par critères
- ✅ `testFindByWithOrderBy()` : Teste le tri des résultats
- ✅ `testFindByWithLimit()` : Teste la limitation des résultats
- ✅ `testFindByWithOffset()` : Teste le pagination
- ✅ `testHydrateWithDateTime()` : Teste l'hydratation des dates
- ✅ `testHydrateWithBoolean()` : Teste l'hydratation des booléens
- ✅ `testFindByWithInvalidIdentifier()` : Teste la validation des identifiants

### MetadataReaderTest
- ✅ `testGetMetadata()` : Teste la lecture des métadonnées
- ✅ `testGetTableName()` : Teste la récupération du nom de table
- ✅ `testGetIdProperty()` : Teste la récupération de la propriété ID
- ✅ `testIndexMetadata()` : Teste la lecture des index

### QueryBuilderTest
- ✅ `testSelect()` : Teste la sélection de colonnes
- ✅ `testFrom()` : Teste la clause FROM
- ✅ `testWhere()` : Teste les conditions WHERE
- ✅ `testAndWhere()` : Teste les conditions AND
- ✅ `testOrWhere()` : Teste les conditions OR
- ✅ `testJoin()` : Teste les JOIN
- ✅ `testLeftJoin()` : Teste les LEFT JOIN
- ✅ `testOrderBy()` : Teste le tri
- ✅ `testGroupBy()` : Teste le GROUP BY
- ✅ `testSetMaxResults()` : Teste la limitation
- ✅ `testSetFirstResult()` : Teste l'offset
- ✅ `testSetParameter()` : Teste les paramètres nommés
- ✅ `testGetOneOrNullResult()` : Teste la récupération d'un seul résultat
- ✅ `testInvalidIdentifier()` : Teste la validation des identifiants
- ✅ `testInvalidJoinType()` : Teste la validation des types de JOIN

### MigrationGeneratorTest
- ✅ `testGenerateCreateTable()` : Teste la génération de CREATE TABLE
- ✅ `testGenerateWithIndex()` : Teste la génération d'index
- ✅ `testGenerateWithUniqueIndex()` : Teste la génération d'index uniques
- ✅ `testGenerateWithAutoIncrement()` : Teste AUTO_INCREMENT
- ✅ `testGenerateWithNullable()` : Teste les colonnes nullable
- ✅ `testGenerateWithDefault()` : Teste les valeurs par défaut
- ✅ `testGenerateAlterTable()` : Teste la génération d'ALTER TABLE
- ✅ `testGenerateMultipleEntities()` : Teste la génération pour plusieurs entités
- ✅ `testGenerateWithDateTime()` : Teste les colonnes datetime
- ✅ `testGenerateWithBoolean()` : Teste les colonnes boolean
- ✅ `testGenerateWithCustomColumnName()` : Teste les noms de colonnes personnalisés
- ✅ `testGenerateWithLength()` : Teste les longueurs de colonnes

## Configuration

Les tests utilisent SQLite en mémoire (`:memory:`) pour une exécution rapide et isolée.

## Couverture de Code

La couverture de code peut être générée avec :
```bash
composer test-coverage
```

## Notes

- Tous les tests sont isolés et utilisent une base de données en mémoire
- Les tests sont indépendants les uns des autres
- Chaque test crée ses propres données de test
- Les tests vérifient à la fois les fonctionnalités et la sécurité (validation des identifiants)

