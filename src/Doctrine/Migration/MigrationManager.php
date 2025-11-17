<?php

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
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->connection->execute($sql);
        } catch (\Exception $e) {
            throw new MigrationException(
                "Impossible de créer la table de migrations: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Enregistre une migration comme exécutée
     * 
     * @param string $migrationName Nom de la migration
     */
    public function markAsExecuted(string $migrationName): void
    {
        $sql = "INSERT INTO `{$this->migrationsTable}` (`migration`, `executed_at`) 
                VALUES (:migration, NOW())
                ON DUPLICATE KEY UPDATE `executed_at` = NOW()";
        
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
     * Vérifie si une migration a déjà été exécutée
     * 
     * @param string $migrationName Nom de la migration
     * @return bool True si la migration a été exécutée
     */
    public function isExecuted(string $migrationName): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM `{$this->migrationsTable}` 
                WHERE `migration` = :migration";
        
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
     * @return array Liste des noms de migrations
     */
    public function getExecutedMigrations(): array
    {
        $sql = "SELECT `migration` FROM `{$this->migrationsTable}` ORDER BY `executed_at` ASC";
        
        try {
            $rows = $this->connection->fetchAll($sql);
            return array_column($rows, 'migration');
        } catch (\Exception $e) {
            return [];
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

