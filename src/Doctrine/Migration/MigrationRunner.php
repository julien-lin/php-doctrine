<?php

namespace JulienLinard\Doctrine\Migration;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Migration\Exceptions\MigrationException;

/**
 * ============================================
 * MIGRATION RUNNER
 * ============================================
 * 
 * Exécute les migrations SQL de manière sécurisée.
 * 
 * FONCTIONNALITÉS :
 * - Exécute les migrations SQL
 * - Gère les transactions
 * - Sépare les requêtes multiples
 * - Gère les erreurs proprement
 */
class MigrationRunner
{
    private Connection $connection;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Exécute une migration SQL
     * 
     * @param string $sql SQL à exécuter
     * @param bool $useTransaction Utiliser une transaction (défaut: true)
     * @throws MigrationException Si l'exécution échoue
     */
    public function run(string $sql, bool $useTransaction = true): void
    {
        if (empty(trim($sql))) {
            return;
        }
        
        try {
            if ($useTransaction) {
                $this->connection->beginTransaction();
            }
            
            // Séparer les requêtes multiples
            $queries = $this->splitQueries($sql);
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $this->connection->execute($query);
                }
            }
            
            if ($useTransaction) {
                $this->connection->commit();
            }
        } catch (\Exception $e) {
            if ($useTransaction && $this->connection->inTransaction()) {
                $this->connection->rollback();
            }
            
            throw new MigrationException(
                "Erreur lors de l'exécution de la migration: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Sépare les requêtes SQL multiples en tenant compte des chaînes
     * 
     * @param string $sql SQL contenant potentiellement plusieurs requêtes
     * @return array Tableau de requêtes SQL
     */
    private function splitQueries(string $sql): array
    {
        $queries = [];
        $currentQuery = '';
        $inString = false;
        $stringChar = null;
        $i = 0;
        $len = strlen($sql);
        
        while ($i < $len) {
            $char = $sql[$i];
            $prevChar = $i > 0 ? $sql[$i - 1] : null;
            
            // Gérer les chaînes de caractères
            if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                    $stringChar = null;
                }
            }
            
            $currentQuery .= $char;
            
            // Si on trouve un point-virgule et qu'on n'est pas dans une chaîne
            if ($char === ';' && !$inString) {
                $queries[] = trim($currentQuery);
                $currentQuery = '';
            }
            
            $i++;
        }
        
        // Ajouter la dernière requête si elle existe
        if (!empty(trim($currentQuery))) {
            $queries[] = trim($currentQuery);
        }
        
        return $queries;
    }
}

