<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Migration;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Migration\Exceptions\MigrationException;

/**
 * ============================================
 * MIGRATION MANAGER
 * ============================================
 * 
 * Gère l'historique des migrations appliquées.
 * 
 * FONCTIONNALITÉS :
 * - Crée la table de suivi des migrations
 * - Enregistre les migrations exécutées
 * - Vérifie si une migration a été appliquée
 * - Génère des noms de migration uniques
 */
class MigrationManager
{
    private Connection $connection;
    private string $migrationsTable = 'doctrine_migrations';
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->ensureMigrationsTable();
    }
    
    /**
     * Crée la table de suivi des migrations si elle n'existe pas
     */
    private function ensureMigrationsTable(): void
    {
        // Vérifier si la table existe déjà
        $tableExists = $this->tableExists($this->migrationsTable);
        $driver = $this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        if (!$tableExists) {
            // Créer la table avec la nouvelle structure
            if ($driver === 'sqlite') {
                $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `migration` VARCHAR(255) NOT NULL UNIQUE,
                    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `rolled_back` INTEGER NOT NULL DEFAULT 0,
                    `rolled_back_at` DATETIME NULL
                )";
            } else {
                $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `migration` VARCHAR(255) NOT NULL UNIQUE,
                    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `rolled_back` TINYINT(1) NOT NULL DEFAULT 0,
                    `rolled_back_at` DATETIME NULL,
                    INDEX `idx_migration` (`migration`),
                    INDEX `idx_rolled_back` (`rolled_back`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            }
            
            try {
                $this->connection->execute($sql);
            } catch (\Exception $e) {
                throw new MigrationException(
                    "Impossible de créer la table de migrations: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        } else {
            // Migrer la table existante pour ajouter les colonnes de rollback
            $this->migrateMigrationsTable();
        }
    }
    
    /**
     * Migre la table de migrations pour ajouter le support des rollbacks
     */
    private function migrateMigrationsTable(): void
    {
        // Vérifier si les colonnes existent déjà
        $columns = $this->getTableColumns($this->migrationsTable);
        $hasRolledBack = false;
        $hasRolledBackAt = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'rolled_back') {
                $hasRolledBack = true;
            }
            if ($column['Field'] === 'rolled_back_at') {
                $hasRolledBackAt = true;
            }
        }
        
        // Ajouter les colonnes manquantes
        if (!$hasRolledBack) {
            try {
                $this->connection->execute(
                    "ALTER TABLE `{$this->migrationsTable}` ADD COLUMN `rolled_back` TINYINT(1) NOT NULL DEFAULT 0"
                );
            } catch (\Exception $e) {
                // Ignorer si la colonne existe déjà
            }
        }
        
        if (!$hasRolledBackAt) {
            try {
                $this->connection->execute(
                    "ALTER TABLE `{$this->migrationsTable}` ADD COLUMN `rolled_back_at` DATETIME NULL"
                );
            } catch (\Exception $e) {
                // Ignorer si la colonne existe déjà
            }
        }
        
        // Ajouter l'index si nécessaire
        try {
            $this->connection->execute(
                "ALTER TABLE `{$this->migrationsTable}` ADD INDEX `idx_rolled_back` (`rolled_back`)"
            );
        } catch (\Exception $e) {
            // Ignorer si l'index existe déjà
        }
    }
    
    /**
     * Vérifie si une table existe
     */
    private function tableExists(string $tableName): bool
    {
        $driver = $this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
            $result = $this->connection->fetchOne($sql, [$tableName]);
            return $result !== null;
        } else {
            // Utiliser INFORMATION_SCHEMA qui supporte les paramètres préparés
            // Récupérer le nom de la base depuis la connexion PDO
            try {
                $stmt = $this->connection->getPdo()->query('SELECT DATABASE()');
                $dbName = $stmt ? $stmt->fetchColumn() : null;
            } catch (\Exception $e) {
                $dbName = null;
            }
            
            if ($dbName) {
                // Utiliser TABLE_SCHEMA pour plus de précision
                $sql = "SELECT COUNT(*) as count 
                        FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
                $result = $this->connection->fetchOne($sql, [$dbName, $tableName]);
                return isset($result['count']) && (int)$result['count'] > 0;
            } else {
                // Fallback : chercher sans TABLE_SCHEMA (fonctionne aussi)
                $sql = "SELECT COUNT(*) as count 
                        FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_NAME = ?";
                $result = $this->connection->fetchOne($sql, [$tableName]);
                return isset($result['count']) && (int)$result['count'] > 0;
            }
        }
    }
    
    /**
     * Récupère les colonnes d'une table
     */
    private function getTableColumns(string $tableName): array
    {
        $driver = $this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $sql = "PRAGMA table_info({$tableName})";
            $result = $this->connection->fetchAll($sql);
            return array_map(function($row) {
                return ['Field' => $row['name'], 'Type' => $row['type']];
            }, $result);
        } else {
            $sql = "SHOW COLUMNS FROM `{$tableName}`";
            return $this->connection->fetchAll($sql);
        }
    }
    
    /**
     * Enregistre une migration comme exécutée
     * 
     * @param string $migrationName Nom de la migration
     */
    public function markAsExecuted(string $migrationName): void
    {
        $sql = "INSERT INTO `{$this->migrationsTable}` (`migration`, `executed_at`, `rolled_back`) 
                VALUES (:migration, NOW(), 0)
                ON DUPLICATE KEY UPDATE `executed_at` = NOW(), `rolled_back` = 0";
        
        try {
            $this->connection->execute($sql, ['migration' => $migrationName]);
        } catch (\Exception $e) {
            throw new MigrationException(
                "Impossible d'enregistrer la migration: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Marque une migration comme annulée (rollback)
     * 
     * @param string $migrationName Nom de la migration
     */
    public function markAsRolledBack(string $migrationName): void
    {
        $sql = "UPDATE `{$this->migrationsTable}` 
                SET `rolled_back` = 1, `rolled_back_at` = NOW()
                WHERE `migration` = :migration";
        
        try {
            $this->connection->execute($sql, ['migration' => $migrationName]);
        } catch (\Exception $e) {
            throw new MigrationException(
                "Impossible d'enregistrer le rollback: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Vérifie si une migration a déjà été exécutée (et non annulée)
     * 
     * @param string $migrationName Nom de la migration
     * @return bool True si la migration a été exécutée et n'est pas annulée
     */
    public function isExecuted(string $migrationName): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM `{$this->migrationsTable}` 
                WHERE `migration` = :migration AND `rolled_back` = 0";
        
        try {
            $result = $this->connection->fetchOne($sql, ['migration' => $migrationName]);
            return isset($result['count']) && (int)$result['count'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Récupère toutes les migrations exécutées
     * 
     * @param bool $includeRolledBack Inclure les migrations annulées
     * @return array Liste des noms de migrations
     */
    public function getExecutedMigrations(bool $includeRolledBack = false): array
    {
        if ($includeRolledBack) {
            $sql = "SELECT `migration` FROM `{$this->migrationsTable}` ORDER BY `executed_at` ASC";
        } else {
            $sql = "SELECT `migration` FROM `{$this->migrationsTable}` 
                    WHERE `rolled_back` = 0 
                    ORDER BY `executed_at` ASC";
        }
        
        try {
            $rows = $this->connection->fetchAll($sql);
            return array_column($rows, 'migration');
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Récupère les migrations exécutées mais non annulées (pour rollback)
     * 
     * @param int|null $limit Nombre maximum de migrations à retourner (pour --steps)
     * @return array Liste des noms de migrations (du plus récent au plus ancien)
     */
    public function getMigrationsToRollback(?int $limit = null): array
    {
        $sql = "SELECT `migration` FROM `{$this->migrationsTable}` 
                WHERE `rolled_back` = 0 
                ORDER BY `executed_at` DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        try {
            $rows = $this->connection->fetchAll($sql);
            return array_column($rows, 'migration');
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Vérifie si une migration a été annulée
     * 
     * @param string $migrationName Nom de la migration
     * @return bool True si la migration a été annulée
     */
    public function isRolledBack(string $migrationName): bool
    {
        $sql = "SELECT `rolled_back` FROM `{$this->migrationsTable}` 
                WHERE `migration` = :migration";
        
        try {
            $result = $this->connection->fetchOne($sql, ['migration' => $migrationName]);
            return isset($result['rolled_back']) && (int)$result['rolled_back'] === 1;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Génère un nom de migration unique basé sur la date/heure
     * 
     * @return string Nom de migration (format: Migration_YYYYMMDDHHMMSS)
     */
    public function generateMigrationName(): string
    {
        return 'Migration_' . date('YmdHis');
    }
    
    /**
     * Retourne le nom de la table de migrations
     * 
     * @return string Nom de la table
     */
    public function getMigrationsTable(): string
    {
        return $this->migrationsTable;
    }
}

