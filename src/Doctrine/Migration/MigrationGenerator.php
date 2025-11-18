<?php

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
        
        // Vérifier si la table existe
        if (!$this->tableExists($tableName)) {
            // Créer la table
            return $this->generateCreateTableSQL($metadata);
        } else {
            // Comparer et générer les ALTER TABLE
            return $this->generateAlterTableSQL($metadata);
        }
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
        
        foreach ($entityClasses as $entityClass) {
            $sql = $this->generateForEntity($entityClass);
            if (!empty($sql)) {
                $sqlParts[] = $sql;
            }
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
        $result = $this->connection->fetchOne("SELECT DATABASE() as db_name");
        return $result['db_name'] ?? '';
    }
    
    /**
     * Génère le SQL pour créer une table
     * 
     * @param array $metadata Métadonnées de l'entité
     * @return string SQL CREATE TABLE
     */
    private function generateCreateTableSQL(array $metadata): string
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
                    $columns[] = "`{$fkColumn}` INT NOT NULL";
                }
                
                // Récupérer les métadonnées de l'entité cible
                try {
                    $targetMetadata = $this->metadataReader->getMetadata($relation['targetEntity']);
                    $targetIdColumn = $targetMetadata['id'];
                    $targetIdInfo = $targetMetadata['columns'][$targetIdColumn] ?? null;
                    $targetIdName = $targetIdInfo['name'] ?? $targetIdColumn;
                    
                    $foreignKeys[] = "CONSTRAINT `fk_{$metadata['table']}_{$fkColumn}` 
                                     FOREIGN KEY (`{$fkColumn}`) 
                                     REFERENCES `{$targetMetadata['table']}` (`{$targetIdName}`) 
                                     ON DELETE CASCADE";
                } catch (\Exception $e) {
                    // Si l'entité cible n'existe pas encore, on ne crée pas la FK
                    // Elle sera créée lors d'une migration ultérieure
                }
            }
        }
        
        // Index sur les colonnes importantes
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            $columnName = $columnInfo['name'] ?? $propertyName;
            if (in_array($columnName, ['user_id', 'email', 'created_at', 'deadline', 'is_urgent', 'completed'])) {
                $indexes[] = "INDEX `idx_{$columnName}` (`{$columnName}`)";
            }
        }
        
        // Construire le SQL
        $sql = "CREATE TABLE IF NOT EXISTS `{$metadata['table']}` (\n";
        $sql .= "  " . implode(",\n  ", $columns);
        
        if ($primaryKey) {
            $idColumnName = $metadata['columns'][$primaryKey]['name'] ?? $primaryKey;
            $sql .= ",\n  PRIMARY KEY (`{$idColumnName}`)";
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
            
            if (!isset($existingColumns[$columnName])) {
                // Colonne à ajouter
                $alterations[] = "ADD COLUMN " . $this->generateColumnSQL($propertyName, $columnInfo);
            } else {
                // Vérifier si la colonne doit être modifiée
                $existingColumn = $existingColumns[$columnName];
                if ($this->columnNeedsUpdate($columnInfo, $existingColumn)) {
                    $alterations[] = "MODIFY COLUMN " . $this->generateColumnSQL($propertyName, $columnInfo);
                }
            }
        }
        
        if (empty($alterations)) {
            return '';
        }
        
        return "ALTER TABLE `{$metadata['table']}`\n  " . implode(",\n  ", $alterations) . ";";
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
        
        return "`{$columnName}` {$type} {$nullable}{$default}{$autoIncrement}";
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
            // Logger l'erreur au lieu de retourner un tableau vide
            error_log("Erreur lors de la récupération des colonnes pour {$tableName}: " . $e->getMessage());
            throw new \RuntimeException(
                "Impossible de récupérer les colonnes existantes pour la table {$tableName}: " . $e->getMessage(),
                0,
                $e
            );
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
     * @return string Identifiant échappé
     */
    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }
}

