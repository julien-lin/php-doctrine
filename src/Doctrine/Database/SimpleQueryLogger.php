<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Database;

/**
 * Implémentation simple d'un logger de requêtes SQL
 */
class SimpleQueryLogger implements QueryLoggerInterface
{
    private bool $enabled = false;
    private array $logs = [];
    private ?string $logFile = null;
    private bool $logToConsole = false;

    /**
     * Constructeur
     * 
     * @param bool $enabled Activer le logging par défaut
     * @param string|null $logFile Chemin vers le fichier de log (optionnel)
     * @param bool $logToConsole Logger aussi dans la console (pour debug)
     */
    public function __construct(bool $enabled = false, ?string $logFile = null, bool $logToConsole = false)
    {
        $this->enabled = $enabled;
        $this->logFile = $logFile;
        $this->logToConsole = $logToConsole;
    }

    /**
     * Log une requête SQL exécutée
     */
    public function log(string $sql, array $params = [], float $executionTime = 0.0): void
    {
        if (!$this->enabled) {
            return;
        }

        $logEntry = [
            'sql' => $sql,
            'params' => $params,
            'time' => $executionTime,
            'timestamp' => microtime(true),
        ];

        $this->logs[] = $logEntry;

        // Logger dans le fichier si configuré
        if ($this->logFile !== null) {
            $this->writeToFile($logEntry);
        }

        // Logger dans la console si activé
        if ($this->logToConsole) {
            $this->writeToConsole($logEntry);
        }
    }

    /**
     * Écrit un log dans le fichier
     */
    private function writeToFile(array $logEntry): void
    {
        $formatted = $this->formatLogEntry($logEntry);
        file_put_contents($this->logFile, $formatted . PHP_EOL, FILE_APPEND);
    }

    /**
     * Écrit un log dans la console (stderr pour ne pas polluer stdout)
     */
    private function writeToConsole(array $logEntry): void
    {
        $formatted = $this->formatLogEntry($logEntry);
        error_log($formatted);
    }

    /**
     * Formate une entrée de log
     */
    private function formatLogEntry(array $logEntry): string
    {
        $time = number_format($logEntry['time'] * 1000, 2) . 'ms';
        $params = !empty($logEntry['params']) 
            ? ' [' . json_encode($logEntry['params'], JSON_UNESCAPED_UNICODE) . ']' 
            : '';
        
        return sprintf(
            '[%s] %s%s (%s)',
            date('Y-m-d H:i:s'),
            $logEntry['sql'],
            $params,
            $time
        );
    }

    /**
     * Active ou désactive le logging
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Vérifie si le logging est activé
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Retourne tous les logs enregistrés
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Vide les logs
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    /**
     * Retourne le nombre de requêtes loggées
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * Retourne le temps total d'exécution de toutes les requêtes
     */
    public function getTotalTime(): float
    {
        return array_sum(array_column($this->logs, 'time'));
    }
}
