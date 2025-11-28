<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Database;

use PDO;
use PDOException;

/**
 * Gestionnaire de connexion à la base de données
 */
class Connection
{
    private ?PDO $pdo = null;
    private array $config;
    private bool $inTransaction = false;

    /**
     * Constructeur
     *
     * @param array $config Configuration de la base de données
     */
    public function __construct(array $config)
    {
        $this->config = $this->normalizeConfig($config);
    }

    /**
     * Normalise la configuration de la base de données
     */
    private function normalizeConfig(array $config): array
    {
        return [
            'driver' => $config['driver'] ?? 'mysql',
            'host' => $config['host'] ?? 'localhost',
            'port' => $config['port'] ?? null,
            'dbname' => $config['dbname'] ?? $config['database'] ?? '',
            'user' => $config['user'] ?? $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'options' => $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ];
    }

    /**
     * Retourne l'instance PDO (crée la connexion si nécessaire)
     *
     * @return PDO Instance PDO
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Établit la connexion à la base de données
     */
    private function connect(): void
    {
        $dsn = $this->buildDsn();
        
        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                $this->config['options']
            );
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Impossible de se connecter à la base de données: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Construit le DSN selon le driver
     */
    private function buildDsn(): string
    {
        $driver = $this->config['driver'];
        $host = $this->config['host'];
        $port = $this->config['port'];
        $dbname = $this->config['dbname'];
        $charset = $this->config['charset'];

        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s%s;dbname=%s;charset=%s',
                $host,
                $port ? ';port=' . $port : '',
                $dbname,
                $charset
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s%s;dbname=%s',
                $host,
                $port ? ';port=' . $port : '',
                $dbname
            ),
            'sqlite' => sprintf('sqlite:%s', $dbname),
            default => throw new \InvalidArgumentException("Driver non supporté: {$driver}"),
        };
    }

    /**
     * Exécute une requête SQL
     *
     * @param string $sql Requête SQL
     * @param array $params Paramètres
     * @return \PDOStatement Statement exécuté
     */
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Exécute une requête et retourne tous les résultats
     *
     * @param string $sql Requête SQL
     * @param array $params Paramètres
     * @return array Résultats
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Exécute une requête et retourne un seul résultat
     *
     * @param string $sql Requête SQL
     * @param array $params Paramètres
     * @return array|null Résultat ou null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction(): void
    {
        $pdo = $this->getPdo();
        
        // Vérifier via PDO directement pour éviter les désynchronisations
        if ($pdo->inTransaction()) {
            throw new \RuntimeException('Une transaction est déjà en cours.');
        }
        
        $pdo->beginTransaction();
        $this->inTransaction = true;
    }

    /**
     * Valide une transaction
     */
    public function commit(): void
    {
        $pdo = $this->getPdo();
        
        // Vérifier via PDO directement pour éviter les désynchronisations
        if (!$pdo->inTransaction()) {
            throw new \RuntimeException('Aucune transaction en cours.');
        }
        
        $pdo->commit();
        $this->inTransaction = false;
    }

    /**
     * Annule une transaction
     */
    public function rollback(): void
    {
        $pdo = $this->getPdo();
        
        // Vérifier via PDO directement pour éviter les désynchronisations
        if (!$pdo->inTransaction()) {
            throw new \RuntimeException('Aucune transaction en cours.');
        }
        
        $pdo->rollBack();
        $this->inTransaction = false;
    }

    /**
     * Vérifie si une transaction est en cours
     * 
     * Utilise PDO::inTransaction() pour une vérification fiable
     * et synchronise l'état local si nécessaire
     */
    public function inTransaction(): bool
    {
        if ($this->pdo === null) {
            return false;
        }
        
        // Utiliser PDO::inTransaction() pour une vérification fiable
        $pdoInTransaction = $this->pdo->inTransaction();
        
        // Synchroniser l'état local si nécessaire
        if ($pdoInTransaction !== $this->inTransaction) {
            $this->inTransaction = $pdoInTransaction;
        }
        
        return $pdoInTransaction;
    }

    /**
     * Retourne le dernier ID inséré
     *
     * @param string|null $name Nom de la séquence (pour PostgreSQL)
     * @return string|false Dernier ID inséré
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * Ferme la connexion
     */
    public function close(): void
    {
        $this->pdo = null;
    }
}

