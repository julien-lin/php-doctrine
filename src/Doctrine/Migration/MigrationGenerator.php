<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Migration;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Metadata\MetadataReader;

/**
 * ============================================
 * MIGRATION GENERATOR
 * ============================================
 * 
 * Génère les migrations SQL à partir des entités Doctrine.
 * 
 * FONCTIONNALITÉS :
 * - Analyse les entités via MetadataReader
 * - Compare avec l'état actuel de la base de données
 * - Génère les CREATE TABLE
 * - Génère les ALTER TABLE pour les modifications
 */
class MigrationGenerator
{
    private Connection $connection;
    private MetadataReader $metadataReader;
    
    public function __construct(Connection $connection, MetadataReader $metadataReader)
    {
        $this->connection = $connection;
        $this->metadataReader = $metadataReader;
    }
    
    /**
     * Génère une migration pour une entité
     * 
     * @param string $entityClass Classe de l'entité
     * @return string SQL de migration ou chaîne vide si aucune migration nécessaire
     */
    public function generateForEntity(string $entityClass): string
    {
        $metadata = $this->metadataReader->getMetadata($entityClass);
        $tableName = $metadata['table'];
        
        $sqlParts = [];
        
        // Vérifier si la table existe
        if (!$this->tableExists($tableName)) {
            // Créer la table
            $tableSql = $this->generateCreateTableSQL($metadata);
            if (!empty($tableSql)) {
                $sqlParts[] = $tableSql;
            }
        } else {
            // Comparer et générer les ALTER TABLE
            $alterSql = $this->generateAlterTableSQL($metadata);
            if (!empty($alterSql)) {
                $sqlParts[] = $alterSql;
            }
        }
        
        // Générer les tables de jointure pour les relations ManyToMany de cette entité
        $joinTablesSql = $this->generateManyToManyJoinTables([$entityClass]);
        if (!empty($joinTablesSql)) {
            $sqlParts[] = $joinTablesSql;
        }
        
        return implode("\n\n", $sqlParts);
    }
    
    /**
     * Génère les migrations pour plusieurs entités
     * 
     * @param array $entityClasses Tableau de classes d'entités
     * @return string SQL combiné de toutes les migrations
     */
    public function generateForEntities(array $entityClasses): string
    {
        $sqlParts = [];
        
        // Trier les entités par ordre de dépendance (topological sort)
        // Les entités sans dépendances sont créées en premier
        $sortedEntities = $this->sortEntitiesByDependencies($entityClasses);
        
        // Générer les migrations pour les tables principales dans le bon ordre
        foreach ($sortedEntities as $entityClass) {
            $metadata = $this->metadataReader->getMetadata($entityClass);
            $tableName = $metadata['table'];
            
            // Vérifier si la table existe
            if (!$this->tableExists($tableName)) {
                // Créer la table (sans les tables de jointure pour l'instant)
                // Passer la liste des entités en cours de création et l'entité actuelle pour vérifier les FK
                $tableSql = $this->generateCreateTableSQL($metadata, $sortedEntities, $entityClass);
                if (!empty($tableSql)) {
                    $sqlParts[] = $tableSql;
                }
            } else {
                // Comparer et générer les ALTER TABLE
                $alterSql = $this->generateAlterTableSQL($metadata);
                if (!empty($alterSql)) {
                    $sqlParts[] = $alterSql;
                }
            }
        }
        
        // Générer les tables de jointure pour les relations ManyToMany (après toutes les tables principales)
        $joinTablesSql = $this->generateManyToManyJoinTables($entityClasses);
        if (!empty($joinTablesSql)) {
            $sqlParts[] = $joinTablesSql;
        }
        
        return implode("\n\n", $sqlParts);
    }
    
    /**
     * Vérifie si une table existe en base de données
     * 
     * @param string $tableName Nom de la table
     * @return bool True si la table existe
     */
    private function tableExists(string $tableName): bool
    {
        $dbName = $this->getDatabaseName();
        
        $sql = "SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = :db_name 
                AND table_name = :table_name";
        
        try {
            $result = $this->connection->fetchOne($sql, [
                'db_name' => $dbName,
                'table_name' => $tableName
            ]);
            
            return isset($result['count']) && (int)$result['count'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Récupère le nom de la base de données actuelle
     * 
     * @return string Nom de la base de données
     */
    private function getDatabaseName(): string
    {
        $pdo = $this->connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        return match ($driver) {
            'mysql' => $pdo->query("SELECT DATABASE()")->fetchColumn() ?: '',
            'pgsql' => $pdo->query("SELECT current_database()")->fetchColumn() ?: '',
            'sqlite' => ':memory:', // SQLite n'a pas de nom de base de données
            default => '',
        };
    }
    
    /**
     * Génère le SQL pour créer une table
     * 
     * @param array $metadata Métadonnées de l'entité
     * @param array|null $entitiesBeingCreated Liste des entités en cours de création (pour vérifier les FK)
     * @param string|null $currentEntityClass Classe de l'entité actuelle (pour déterminer l'ordre)
     * @return string SQL CREATE TABLE
     */
    private function generateCreateTableSQL(array $metadata, ?array $entitiesBeingCreated = null, ?string $currentEntityClass = null): string
    {
        $tableName = $this->escapeIdentifier($metadata['table']);
        $columns = [];
        $primaryKey = null;
        $indexes = [];
        $foreignKeys = [];
        
        // Colonne ID
        if ($metadata['id']) {
            $idColumn = $metadata['columns'][$metadata['id']] ?? null;
            if ($idColumn) {
                $columns[] = $this->generateColumnSQL($metadata['id'], $idColumn, true);
                $primaryKey = $metadata['id'];
            }
        }
        
        // Autres colonnes
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            if ($propertyName !== $metadata['id']) {
                $columns[] = $this->generateColumnSQL($propertyName, $columnInfo);
            }
        }
        
        // Relations ManyToOne (clés étrangères)
        foreach ($metadata['relations'] as $propertyName => $relation) {
            if ($relation['type'] === 'ManyToOne') {
                $fkColumn = $relation['joinColumn'] ?? ($propertyName . '_id');
                
                // Vérifier si la colonne FK existe déjà dans les colonnes
                $fkExists = false;
                foreach ($metadata['columns'] as $colName => $colInfo) {
                    if (($colInfo['name'] ?? $colName) === $fkColumn) {
                        $fkExists = true;
                        break;
                    }
                }
                
                if (!$fkExists) {
                    $fkColumnEscaped = $this->escapeIdentifier($fkColumn);
                    $columns[] = "{$fkColumnEscaped} INT NOT NULL";
                }
                
                // OPTIMISATION : Ajouter automatiquement un index sur la colonne de jointure
                // Les foreign keys sont très utilisées dans les requêtes de relations
                $fkColumnEscaped = $this->escapeIdentifier($fkColumn);
                $fkIndexName = 'idx_' . $metadata['table'] . '_' . $fkColumn;
                $fkIndexNameEscaped = $this->escapeIdentifier($fkIndexName);
                
                // Vérifier qu'un index n'existe pas déjà pour cette colonne
                $indexExists = false;
                foreach ($metadata['indexes'] ?? [] as $indexInfo) {
                    if ($indexInfo['column'] === $fkColumn) {
                        $indexExists = true;
                        break;
                    }
                }
                
                if (!$indexExists) {
                    $indexes[] = "INDEX {$fkIndexNameEscaped} ({$fkColumnEscaped})";
                }
                
                // Récupérer les métadonnées de l'entité cible
                try {
                    $targetMetadata = $this->metadataReader->getMetadata($relation['targetEntity']);
                    $targetTableName = $targetMetadata['table'];
                    
                    // Vérifier si la table cible existe déjà OU si elle sera créée dans cette migration
                    $targetTableExists = $this->tableExists($targetTableName);
                    $targetWillBeCreated = false;
                    
                    if ($entitiesBeingCreated !== null && $currentEntityClass !== null) {
                        // Vérifier si l'entité cible est dans la liste des entités à créer
                        // et si elle sera créée avant l'entité actuelle (grâce au tri topologique)
                        $currentIndex = array_search($currentEntityClass, $entitiesBeingCreated);
                        $targetIndex = array_search($relation['targetEntity'], $entitiesBeingCreated);
                        
                        // Si la table cible sera créée avant la table actuelle, on peut créer la FK
                        if ($targetIndex !== false && $currentIndex !== false && $targetIndex < $currentIndex) {
                            $targetWillBeCreated = true;
                        }
                    }
                    
                    // Créer la FK seulement si la table cible existe déjà ou sera créée avant
                    if ($targetTableExists || $targetWillBeCreated) {
                        $targetIdColumn = $targetMetadata['id'];
                        $targetIdInfo = $targetMetadata['columns'][$targetIdColumn] ?? null;
                        $targetIdName = $targetIdInfo['name'] ?? $targetIdColumn;
                        
                        $fkNameEscaped = $this->escapeIdentifier('fk_' . $metadata['table'] . '_' . $fkColumn);
                        $targetTableEscaped = $this->escapeIdentifier($targetTableName);
                        $targetIdEscaped = $this->escapeIdentifier($targetIdName);
                        
                        $foreignKeys[] = "CONSTRAINT {$fkNameEscaped} 
                                         FOREIGN KEY ({$fkColumnEscaped}) 
                                         REFERENCES {$targetTableEscaped} ({$targetIdEscaped}) 
                                         ON DELETE CASCADE";
                    }
                    // Sinon, on ne crée pas la FK maintenant (elle sera créée dans une migration ultérieure)
                } catch (\Exception $e) {
                    // Si l'entité cible n'existe pas encore, on ne crée pas la FK
                    // Elle sera créée lors d'une migration ultérieure
                }
            }
        }
        
        // Index définis via l'attribut #[Index]
        foreach ($metadata['indexes'] ?? [] as $indexInfo) {
            $indexType = $indexInfo['unique'] ? 'UNIQUE INDEX' : 'INDEX';
            $indexNameEscaped = $this->escapeIdentifier($indexInfo['name']);
            $columnNameEscaped = $this->escapeIdentifier($indexInfo['column']);
            $indexes[] = "{$indexType} {$indexNameEscaped} ({$columnNameEscaped})";
        }
        
        // Construire le SQL
        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (\n";
        $sql .= "  " . implode(",\n  ", $columns);
        
        if ($primaryKey) {
            $idColumnName = $metadata['columns'][$primaryKey]['name'] ?? $primaryKey;
            $idColumnEscaped = $this->escapeIdentifier($idColumnName);
            $sql .= ",\n  PRIMARY KEY ({$idColumnEscaped})";
        }
        
        if (!empty($indexes)) {
            $sql .= ",\n  " . implode(",\n  ", $indexes);
        }
        
        if (!empty($foreignKeys)) {
            $sql .= ",\n  " . implode(",\n  ", $foreignKeys);
        }
        
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        return $sql;
    }
    
    /**
     * Génère le SQL pour modifier une table existante
     * 
     * @param array $metadata Métadonnées de l'entité
     * @return string SQL ALTER TABLE ou chaîne vide si aucune modification nécessaire
     */
    private function generateAlterTableSQL(array $metadata): string
    {
        $tableName = $this->escapeIdentifier($metadata['table']);
        $alterations = [];
        
        // Récupérer les colonnes existantes
        $existingColumns = $this->getExistingColumns($metadata['table']);
        
        // Vérifier chaque colonne de l'entité
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            $columnName = $columnInfo['name'] ?? $propertyName;
            
            // Vérifier si c'est la colonne ID (clé primaire)
            $isPrimary = ($propertyName === $metadata['id']);
            
            if (!isset($existingColumns[$columnName])) {
                // Colonne à ajouter
                $alterations[] = "ADD COLUMN " . $this->generateColumnSQL($propertyName, $columnInfo, $isPrimary);
            } else {
                // Vérifier si la colonne doit être modifiée
                $existingColumn = $existingColumns[$columnName];
                if ($this->columnNeedsUpdate($columnInfo, $existingColumn)) {
                    $alterations[] = "MODIFY COLUMN " . $this->generateColumnSQL($propertyName, $columnInfo, $isPrimary);
                }
            }
        }
        
        if (empty($alterations)) {
            return '';
        }
        
        return "ALTER TABLE {$tableName}\n  " . implode(",\n  ", $alterations) . ";";
    }
    
    /**
     * Génère le SQL pour une colonne
     * 
     * @param string $propertyName Nom de la propriété
     * @param array $columnInfo Informations de la colonne
     * @param bool $isPrimary True si c'est la clé primaire
     * @return string Définition SQL de la colonne
     */
    private function generateColumnSQL(string $propertyName, array $columnInfo, bool $isPrimary = false): string
    {
        $columnName = $columnInfo['name'] ?? $propertyName;
        $columnNameEscaped = $this->escapeIdentifier($columnName);
        $type = $this->mapTypeToSQL($columnInfo['type'], $columnInfo['length'] ?? null);
        $nullable = ($columnInfo['nullable'] ?? false) ? 'NULL' : 'NOT NULL';
        $default = '';
        
        if (isset($columnInfo['default'])) {
            $defaultValue = $columnInfo['default'];
            if (is_bool($defaultValue)) {
                $default = " DEFAULT " . ($defaultValue ? '1' : '0');
            } elseif (is_string($defaultValue)) {
                // Utiliser une méthode d'échappement SQL plus sûre
                $escapedValue = $this->escapeStringValue($defaultValue);
                $default = " DEFAULT {$escapedValue}";
            } elseif (is_numeric($defaultValue)) {
                $default = " DEFAULT {$defaultValue}";
            }
        }
        
        $autoIncrement = (($columnInfo['autoIncrement'] ?? false) && $isPrimary) ? ' AUTO_INCREMENT' : '';
        
        return "{$columnNameEscaped} {$type} {$nullable}{$default}{$autoIncrement}";
    }
    
    /**
     * Mappe les types PHP/Doctrine vers les types SQL
     * 
     * @param string $type Type Doctrine
     * @param int|null $length Longueur (pour VARCHAR)
     * @return string Type SQL
     */
    private function mapTypeToSQL(string $type, ?int $length): string
    {
        return match ($type) {
            'string' => $length ? "VARCHAR({$length})" : "VARCHAR(255)",
            'text' => 'TEXT',
            'integer', 'int' => 'INT',
            'boolean', 'bool' => 'TINYINT(1)',
            'datetime' => 'DATETIME',
            'date' => 'DATE',
            'time' => 'TIME',
            'float', 'double' => 'DOUBLE',
            'decimal' => 'DECIMAL(10,2)',
            default => 'VARCHAR(255)',
        };
    }
    
    /**
     * Récupère les colonnes existantes d'une table
     * 
     * @param string $tableName Nom de la table
     * @return array Tableau associatif [nom_colonne => infos_colonne]
     */
    private function getExistingColumns(string $tableName): array
    {
        $pdo = $this->connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        // SQLite utilise une approche différente
        if ($driver === 'sqlite') {
            return $this->getExistingColumnsSQLite($tableName);
        }
        
        $dbName = $this->getDatabaseName();
        
        $sql = "SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, 
                       IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = :db_name 
                AND TABLE_NAME = :table_name";
        
        try {
            $rows = $this->connection->fetchAll($sql, [
                'db_name' => $dbName,
                'table_name' => $tableName
            ]);
            
            $columns = [];
            foreach ($rows as $row) {
                $columns[$row['COLUMN_NAME']] = $row;
            }
            
            return $columns;
        } catch (\Exception $e) {
            // Pour SQLite ou autres erreurs, retourner un tableau vide
            // La table sera considérée comme nouvelle
            return [];
        }
    }
    
    /**
     * Récupère les colonnes existantes d'une table SQLite
     * 
     * @param string $tableName Nom de la table
     * @return array Tableau associatif [nom_colonne => infos_colonne]
     */
    private function getExistingColumnsSQLite(string $tableName): array
    {
        try {
            $sql = "PRAGMA table_info({$this->escapeIdentifier($tableName)})";
            $rows = $this->connection->fetchAll($sql);
            
            $columns = [];
            foreach ($rows as $row) {
                $columns[$row['name']] = [
                    'COLUMN_NAME' => $row['name'],
                    'COLUMN_TYPE' => $row['type'],
                    'DATA_TYPE' => $row['type'],
                    'IS_NULLABLE' => $row['notnull'] == 0 ? 'YES' : 'NO',
                    'COLUMN_DEFAULT' => $row['dflt_value'],
                    'COLUMN_KEY' => $row['pk'] == 1 ? 'PRI' : '',
                    'EXTRA' => $row['pk'] == 1 ? 'auto_increment' : '',
                ];
            }
            
            return $columns;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    
    /**
     * Vérifie si une colonne doit être mise à jour
     * 
     * @param array $columnInfo Informations de la colonne depuis l'entité
     * @param array $existingColumn Informations de la colonne existante en DB
     * @return bool True si la colonne doit être mise à jour
     */
    private function columnNeedsUpdate(array $columnInfo, array $existingColumn): bool
    {
        // 1. Comparer le type complet (COLUMN_TYPE contient le type avec longueur/précision)
        $expectedType = $this->mapTypeToSQL($columnInfo['type'], $columnInfo['length'] ?? null);
        $actualType = strtoupper(trim($existingColumn['COLUMN_TYPE'] ?? $existingColumn['DATA_TYPE']));
        
        // Normaliser les types équivalents pour la comparaison
        // Note: COLUMN_TYPE contient déjà le type complet (ex: "TINYINT(1)", "VARCHAR(255)")
        $expectedTypeNormalized = $this->normalizeSQLTypeForComparison($expectedType);
        $actualTypeNormalized = $this->normalizeSQLTypeForComparison($actualType);
        
        if ($expectedTypeNormalized !== $actualTypeNormalized) {
            return true;
        }
        
        // 2. Comparer nullable
        $expectedNullable = $columnInfo['nullable'] ?? false;
        $actualNullable = $existingColumn['IS_NULLABLE'] === 'YES';
        
        if ($expectedNullable !== $actualNullable) {
            return true;
        }
        
        // 3. Comparer les valeurs par défaut
        $expectedDefault = $this->normalizeDefaultValue($columnInfo['default'] ?? null);
        $actualDefault = $this->normalizeDefaultValue($existingColumn['COLUMN_DEFAULT']);
        
        if ($expectedDefault !== $actualDefault) {
            return true;
        }
        
        // 4. Comparer AUTO_INCREMENT (pour les colonnes ID)
        $expectedAutoIncrement = ($columnInfo['autoIncrement'] ?? false);
        $actualAutoIncrement = strpos(strtolower($existingColumn['EXTRA'] ?? ''), 'auto_increment') !== false;
        
        if ($expectedAutoIncrement !== $actualAutoIncrement) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Normalise les types SQL équivalents pour la comparaison
     * 
     * Cette méthode normalise les types SQL pour permettre une comparaison précise,
     * en tenant compte des équivalences (TINYINT(1) = BOOLEAN, INTEGER = INT, etc.)
     * et en préservant les longueurs/précisions.
     * 
     * @param string $type Type SQL à normaliser (ex: "TINYINT(1)", "VARCHAR(255)", "INT")
     * @return string Type SQL normalisé pour comparaison
     */
    private function normalizeSQLTypeForComparison(string $type): string
    {
        // Normaliser la casse et les espaces
        $type = strtoupper(trim($type));
        
        // Extraire le type de base et la longueur/précision si présente
        if (preg_match('/^(\w+)(?:\((\d+)\))?$/', $type, $matches)) {
            $baseType = $matches[1];
            $length = $matches[2] ?? null;
            
            // Normaliser les types équivalents
            // TINYINT(1) et BOOLEAN sont équivalents
            if ($baseType === 'BOOLEAN' || ($baseType === 'TINYINT' && $length === '1')) {
                return 'TINYINT(1)';
            }
            
            // INTEGER = INT
            if ($baseType === 'INTEGER') {
                $baseType = 'INT';
            }
            
            // Reconstruire le type normalisé
            if ($length !== null) {
                return $baseType . '(' . $length . ')';
            }
            
            return $baseType;
        }
        
        return $type;
    }
    
    /**
     * Normalise les valeurs par défaut pour la comparaison
     * 
     * @param mixed $default Valeur par défaut à normaliser
     * @return string|null Valeur normalisée
     */
    private function normalizeDefaultValue($default): ?string
    {
        if ($default === null) {
            return null;
        }
        
        // Convertir les booléens en 0/1
        if (is_bool($default)) {
            return $default ? '1' : '0';
        }
        
        // Normaliser les chaînes (enlever les guillemets)
        if (is_string($default)) {
            $normalized = trim($default, "'\"");
            // Si c'est un nombre, le retourner tel quel
            if (is_numeric($normalized)) {
                return $normalized;
            }
            return $normalized;
        }
        
        // Convertir en chaîne
        return (string)$default;
    }
    
    /**
     * Échappe une valeur de chaîne pour SQL
     * 
     * @param string $value Valeur à échapper
     * @return string Valeur échappée (avec guillemets)
     */
    private function escapeStringValue(string $value): string
    {
        // Utiliser PDO::quote si disponible, sinon utiliser addslashes avec protection supplémentaire
        try {
            $pdo = $this->connection->getPdo();
            return $pdo->quote($value);
        } catch (\Exception $e) {
            // Fallback : échapper les guillemets simples et les backslashes
            return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }
    }

    /**
     * Échappe un identifiant SQL avec des backticks
     * 
     * @param string $identifier Identifiant à échapper
     * @return string Identifiant échappé avec backticks
     */
    private function escapeIdentifier(string $identifier): string
    {
        // Échapper les backticks en les doublant
        $escaped = str_replace('`', '``', $identifier);
        return "`{$escaped}`";
    }
    
    /**
     * Génère les tables de jointure pour les relations ManyToMany
     * 
     * @param array $entityClasses Tableau de classes d'entités à analyser
     * @return string SQL pour créer les tables de jointure
     */
    private function generateManyToManyJoinTables(array $entityClasses): string
    {
        $joinTables = [];
        $processedJoinTables = []; // Pour éviter les doublons
        
        foreach ($entityClasses as $entityClass) {
            $metadata = $this->metadataReader->getMetadata($entityClass);
            
            // Parcourir les relations ManyToMany
            foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
                if ($relation['type'] === 'ManyToMany') {
                    $joinTableName = $relation['joinTable'];
                    
                    // Si pas de nom de table de jointure spécifié, générer un nom par défaut
                    if (empty($joinTableName)) {
                        $sourceTable = $metadata['table'];
                        $targetMetadata = $this->metadataReader->getMetadata($relation['targetEntity']);
                        $targetTable = $targetMetadata['table'];
                        
                        // Générer un nom de table de jointure (ordre alphabétique pour éviter les doublons)
                        $tables = [$sourceTable, $targetTable];
                        sort($tables);
                        $joinTableName = $tables[0] . '_' . $tables[1];
                    }
                    
                    // Éviter de traiter la même table de jointure deux fois
                    if (isset($processedJoinTables[$joinTableName])) {
                        continue;
                    }
                    
                    // Vérifier si la table de jointure existe déjà
                    if ($this->tableExists($joinTableName)) {
                        continue;
                    }
                    
                    // Récupérer les métadonnées de l'entité cible
                    try {
                        $targetMetadata = $this->metadataReader->getMetadata($relation['targetEntity']);
                        
                        // Déterminer les noms de colonnes
                        $sourceTable = $metadata['table'];
                        $targetTable = $targetMetadata['table'];
                        
                        // Extraire le nom de base (sans 's' final si pluriel)
                        $sourceBase = rtrim($sourceTable, 's');
                        if ($sourceBase === $sourceTable) {
                            $sourceBase = $sourceTable;
                        }
                        
                        $targetBase = rtrim($targetTable, 's');
                        if ($targetBase === $targetTable) {
                            $targetBase = $targetTable;
                        }
                        
                        $sourceIdColumn = $sourceBase . '_id';
                        $targetIdColumn = $targetBase . '_id';
                        
                        // Récupérer les colonnes ID des entités
                        $sourceIdProperty = $metadata['id'];
                        $sourceIdInfo = $metadata['columns'][$sourceIdProperty] ?? null;
                        $sourceIdName = $sourceIdInfo['name'] ?? $sourceIdProperty;
                        
                        $targetIdProperty = $targetMetadata['id'];
                        $targetIdInfo = $targetMetadata['columns'][$targetIdProperty] ?? null;
                        $targetIdName = $targetIdInfo['name'] ?? $targetIdProperty;
                        
                        // Générer le SQL pour la table de jointure
                        $joinTableEscaped = $this->escapeIdentifier($joinTableName);
                        $sourceIdColumnEscaped = $this->escapeIdentifier($sourceIdColumn);
                        $targetIdColumnEscaped = $this->escapeIdentifier($targetIdColumn);
                        $sourceTableEscaped = $this->escapeIdentifier($sourceTable);
                        $targetTableEscaped = $this->escapeIdentifier($targetTable);
                        $sourceIdNameEscaped = $this->escapeIdentifier($sourceIdName);
                        $targetIdNameEscaped = $this->escapeIdentifier($targetIdName);
                        
                        $sql = "CREATE TABLE IF NOT EXISTS {$joinTableEscaped} (\n";
                        $sql .= "  {$sourceIdColumnEscaped} INT NOT NULL,\n";
                        $sql .= "  {$targetIdColumnEscaped} INT NOT NULL,\n";
                        $sql .= "  PRIMARY KEY ({$sourceIdColumnEscaped}, {$targetIdColumnEscaped}),\n";
                        $sql .= "  INDEX {$this->escapeIdentifier('idx_' . $joinTableName . '_' . $sourceIdColumn)} ({$sourceIdColumnEscaped}),\n";
                        $sql .= "  INDEX {$this->escapeIdentifier('idx_' . $joinTableName . '_' . $targetIdColumn)} ({$targetIdColumnEscaped}),\n";
                        $sql .= "  CONSTRAINT {$this->escapeIdentifier('fk_' . $joinTableName . '_' . $sourceIdColumn)} \n";
                        $sql .= "    FOREIGN KEY ({$sourceIdColumnEscaped}) \n";
                        $sql .= "    REFERENCES {$sourceTableEscaped} ({$sourceIdNameEscaped}) \n";
                        $sql .= "    ON DELETE CASCADE,\n";
                        $sql .= "  CONSTRAINT {$this->escapeIdentifier('fk_' . $joinTableName . '_' . $targetIdColumn)} \n";
                        $sql .= "    FOREIGN KEY ({$targetIdColumnEscaped}) \n";
                        $sql .= "    REFERENCES {$targetTableEscaped} ({$targetIdNameEscaped}) \n";
                        $sql .= "    ON DELETE CASCADE\n";
                        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                        
                        $joinTables[] = $sql;
                        $processedJoinTables[$joinTableName] = true;
                    } catch (\Exception $e) {
                        // Si l'entité cible n'existe pas encore, ignorer cette relation
                        // Elle sera créée lors d'une migration ultérieure
                    }
                }
            }
        }
        
        return implode("\n\n", $joinTables);
    }
    
    /**
     * Trie les entités par ordre de dépendance (topological sort)
     * Les entités sans dépendances sont placées en premier
     * 
     * @param array $entityClasses Tableau de classes d'entités
     * @return array Tableau d'entités triées par ordre de dépendance
     */
    private function sortEntitiesByDependencies(array $entityClasses): array
    {
        // Construire le graphe de dépendances
        $dependencies = []; // [entityClass => [dependencies...]]
        $entityMap = []; // [entityClass => metadata]
        
        foreach ($entityClasses as $entityClass) {
            $metadata = $this->metadataReader->getMetadata($entityClass);
            $entityMap[$entityClass] = $metadata;
            $dependencies[$entityClass] = [];
            
            // Analyser les relations ManyToOne pour trouver les dépendances
            foreach ($metadata['relations'] ?? [] as $relation) {
                if ($relation['type'] === 'ManyToOne') {
                    $targetEntity = $relation['targetEntity'];
                    
                    // Si l'entité cible est dans la liste des entités à créer, c'est une dépendance
                    if (in_array($targetEntity, $entityClasses, true)) {
                        $dependencies[$entityClass][] = $targetEntity;
                    }
                }
            }
        }
        
        // Tri topologique (Kahn's algorithm)
        $sorted = [];
        $inDegree = []; // Nombre de dépendances sortantes pour chaque entité (combien d'entités elle dépend)
        
        // Initialiser le degré entrant (nombre de dépendances)
        foreach ($entityClasses as $entityClass) {
            $inDegree[$entityClass] = count($dependencies[$entityClass]);
        }
        
        // Trouver les entités sans dépendances (peuvent être créées en premier)
        $queue = [];
        foreach ($inDegree as $entityClass => $degree) {
            if ($degree === 0) {
                $queue[] = $entityClass;
            }
        }
        
        // Traiter les entités dans l'ordre
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;
            
            // Réduire le degré des entités qui dépendent de celle-ci
            // Si une entité B dépend de A, et qu'on vient de traiter A, alors B a une dépendance de moins
            foreach ($dependencies as $entityClass => $deps) {
                if (in_array($current, $deps, true)) {
                    $inDegree[$entityClass]--;
                    if ($inDegree[$entityClass] === 0) {
                        $queue[] = $entityClass;
                    }
                }
            }
        }
        
        // Si toutes les entités n'ont pas été triées, il y a un cycle
        // Dans ce cas, ajouter les entités restantes à la fin (ordre original)
        foreach ($entityClasses as $entityClass) {
            if (!in_array($entityClass, $sorted, true)) {
                $sorted[] = $entityClass;
            }
        }
        
        return $sorted;
    }
}

