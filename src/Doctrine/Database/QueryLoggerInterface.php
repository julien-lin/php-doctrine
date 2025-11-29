<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Database;

/**
 * Interface pour le logging des requêtes SQL
 */
interface QueryLoggerInterface
{
    /**
     * Log une requête SQL exécutée
     * 
     * @param string $sql Requête SQL
     * @param array $params Paramètres de la requête
     * @param float $executionTime Temps d'exécution en secondes
     * @return void
     */
    public function log(string $sql, array $params = [], float $executionTime = 0.0): void;
    
    /**
     * Active ou désactive le logging
     * 
     * @param bool $enabled État du logging
     * @return void
     */
    public function setEnabled(bool $enabled): void;
    
    /**
     * Vérifie si le logging est activé
     * 
     * @return bool
     */
    public function isEnabled(): bool;
    
    /**
     * Retourne tous les logs enregistrés
     * 
     * @return array Tableau de logs [['sql' => ..., 'params' => ..., 'time' => ...], ...]
     */
    public function getLogs(): array;
    
    /**
     * Vide les logs
     * 
     * @return void
     */
    public function clear(): void;
}
