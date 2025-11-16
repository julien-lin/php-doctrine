<?php

namespace JulienLinard\Doctrine;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Metadata\MetadataReader;
use JulienLinard\Doctrine\Repository\EntityRepository;
use JulienLinard\Doctrine\Repository\RepositoryInterface;

/**
 * Entity Manager - Gestionnaire principal des entités
 */
class EntityManager
{
    private Connection $connection;
    private MetadataReader $metadataReader;
    
    /**
     * Entités en attente de persistance
     */
    private array $toPersist = [];
    
    /**
     * Entités en attente de suppression
     */
    private array $toRemove = [];
    
    /**
     * Cache des repositories
     */
    private array $repositories = [];

    /**
     * Constructeur
     *
     * @param array $config Configuration de la base de données
     */
    public function __construct(array $config)
    {
        $this->connection = new Connection($config);
        $this->metadataReader = new MetadataReader();
    }

    /**
     * Marque une entité pour persistance
     *
     * @param object $entity Entité à persister
     */
    public function persist(object $entity): void
    {
        $this->toPersist[] = $entity;
    }

    /**
     * Marque une entité pour suppression
     *
     * @param object $entity Entité à supprimer
     */
    public function remove(object $entity): void
    {
        $this->toRemove[] = $entity;
    }

    /**
     * Flush toutes les modifications en attente
     */
    public function flush(): void
    {
        // Persister les nouvelles entités
        foreach ($this->toPersist as $entity) {
            $this->insertEntity($entity);
        }
        $this->toPersist = [];

        // Supprimer les entités marquées
        foreach ($this->toRemove as $entity) {
            $this->deleteEntity($entity);
        }
        $this->toRemove = [];
    }

    /**
     * Insère une entité en base de données
     */
    private function insertEntity(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $tableName = $metadata['table'];
        
        $columns = [];
        $values = [];
        $params = [];
        
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            $reflection = new \ReflectionClass($entity);
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            // Ignorer les valeurs null sauf si nullable
            if ($value === null && !$columnInfo['nullable']) {
                continue;
            }
            
            // Ignorer les IDs auto-incrémentés
            if ($propertyName === $metadata['id'] && $columnInfo['autoIncrement']) {
                continue;
            }
            
            $columnName = $columnInfo['name'];
            $columns[] = $columnName;
            $values[] = ":{$columnName}";
            $params[$columnName] = $this->convertToDatabaseValue($value, $columnInfo['type']);
        }
        
        $sql = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        $this->connection->execute($sql, $params);
        
        // Récupérer l'ID généré
        if ($metadata['id'] !== null) {
            $idColumn = $metadata['columns'][$metadata['id']]['name'] ?? $metadata['id'];
            $lastId = $this->connection->lastInsertId();
            if ($lastId) {
                $reflection = new \ReflectionClass($entity);
                $idProperty = $reflection->getProperty($metadata['id']);
                $idProperty->setAccessible(true);
                $idProperty->setValue($entity, (int)$lastId);
            }
        }
    }

    /**
     * Supprime une entité de la base de données
     */
    private function deleteEntity(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $tableName = $metadata['table'];
        $idProperty = $metadata['id'];
        
        if ($idProperty === null) {
            throw new \RuntimeException("Impossible de supprimer une entité sans ID.");
        }
        
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty($idProperty);
        $property->setAccessible(true);
        $id = $property->getValue($entity);
        
        if ($id === null) {
            throw new \RuntimeException("Impossible de supprimer une entité sans ID.");
        }
        
        $idColumn = $metadata['columns'][$idProperty]['name'] ?? $idProperty;
        $sql = "DELETE FROM {$tableName} WHERE {$idColumn} = :id";
        $this->connection->execute($sql, ['id' => $id]);
    }

    /**
     * Trouve une entité par son ID
     *
     * @param string $entityClass Classe de l'entité
     * @param int|string $id Identifiant
     * @return object|null Entité ou null
     */
    public function find(string $entityClass, int|string $id): ?object
    {
        return $this->getRepository($entityClass)->find($id);
    }

    /**
     * Retourne le repository pour une classe d'entité
     *
     * @param string $entityClass Classe de l'entité
     * @return RepositoryInterface Repository
     */
    public function getRepository(string $entityClass): RepositoryInterface
    {
        if (!isset($this->repositories[$entityClass])) {
            $this->repositories[$entityClass] = new EntityRepository(
                $this->connection,
                $this->metadataReader,
                $entityClass
            );
        }
        
        return $this->repositories[$entityClass];
    }

    /**
     * Crée un Query Builder
     *
     * @return QueryBuilder\QueryBuilder Query Builder
     */
    public function createQueryBuilder(): QueryBuilder\QueryBuilder
    {
        return new QueryBuilder\QueryBuilder($this->connection, $this->metadataReader);
    }

    /**
     * Convertit une valeur PHP en valeur de base de données
     */
    private function convertToDatabaseValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'datetime' => $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : $value,
            'date' => $value instanceof \DateTime ? $value->format('Y-m-d') : $value,
            'time' => $value instanceof \DateTime ? $value->format('H:i:s') : $value,
            'boolean', 'bool' => $value ? 1 : 0,
            default => $value,
        };
    }

    /**
     * Retourne la connexion
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit(): void
    {
        $this->connection->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollback(): void
    {
        $this->connection->rollback();
    }
}

