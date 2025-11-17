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
        // Persister les nouvelles entités ou mettre à jour les existantes
        foreach ($this->toPersist as $entity) {
            $className = get_class($entity);
            $metadata = $this->metadataReader->getMetadata($className);
            
            // Vérifier si l'entité a un ID (mise à jour) ou non (insertion)
            if ($metadata['id'] !== null) {
                $reflection = new \ReflectionClass($entity);
                $idProperty = $reflection->getProperty($metadata['id']);
                $idProperty->setAccessible(true);
                $id = $idProperty->getValue($entity);
                
                if ($id !== null && $id !== 0) {
                    // L'entité a un ID, c'est une mise à jour
                    $this->updateEntity($entity);
                } else {
                    // L'entité n'a pas d'ID, c'est une insertion
                    $this->insertEntity($entity);
                }
            } else {
                // Pas d'ID défini, insertion
                $this->insertEntity($entity);
            }
        }
        $this->toPersist = [];

        // Supprimer les entités marquées
        foreach ($this->toRemove as $entity) {
            $this->deleteEntity($entity);
        }
        $this->toRemove = [];
    }

    /**
     * Échappe un identifiant SQL (table ou colonne) avec des backticks
     */
    private function escapeIdentifier(string $identifier): string
    {
        return "`{$identifier}`";
    }

    /**
     * Insère une entité en base de données
     */
    private function insertEntity(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $tableName = $this->escapeIdentifier($metadata['table']);
        
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
            $columns[] = $this->escapeIdentifier($columnName);
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
     * Met à jour une entité en base de données
     */
    private function updateEntity(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $tableName = $this->escapeIdentifier($metadata['table']);
        
        $sets = [];
        $params = [];
        
        // Récupérer l'ID pour la clause WHERE
        $reflection = new \ReflectionClass($entity);
        $idPropertyName = $metadata['id'];
        $idProperty = $reflection->getProperty($idPropertyName);
        $idProperty->setAccessible(true);
        $idValue = $idProperty->getValue($entity);
        
        if ($idValue === null) {
            throw new \RuntimeException("Impossible de mettre à jour une entité sans ID");
        }
        
        $idColumn = $metadata['columns'][$idPropertyName]['name'] ?? $idPropertyName;
        
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            // Ignorer l'ID dans les SET
            if ($propertyName === $idPropertyName) {
                continue;
            }
            
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            // Ignorer les valeurs null sauf si nullable
            if ($value === null && !$columnInfo['nullable']) {
                continue;
            }
            
            $columnName = $columnInfo['name'];
            $sets[] = $this->escapeIdentifier($columnName) . " = :{$columnName}";
            $params[$columnName] = $this->convertToDatabaseValue($value, $columnInfo['type']);
        }
        
        // Ajouter l'ID dans les paramètres pour la clause WHERE
        $params['id'] = $idValue;
        $idColumnEscaped = $this->escapeIdentifier($idColumn);
        
        $sql = "UPDATE {$tableName} SET " . implode(', ', $sets) . " WHERE {$idColumnEscaped} = :id";
        $this->connection->execute($sql, $params);
    }

    /**
     * Supprime une entité de la base de données
     */
    private function deleteEntity(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $tableName = $this->escapeIdentifier($metadata['table']);
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
        
        $idColumn = $this->escapeIdentifier($metadata['columns'][$idProperty]['name'] ?? $idProperty);
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
     * Crée un repository personnalisé avec le MetadataReader partagé
     * 
     * Cette méthode facilite la création de repositories personnalisés en utilisant
     * automatiquement le MetadataReader de l'EntityManager, évitant ainsi la création
     * de multiples instances et améliorant les performances.
     * 
     * @param string $repositoryClass Classe du repository personnalisé (doit étendre EntityRepository)
     * @param string $entityClass Classe de l'entité
     * @return RepositoryInterface Instance du repository
     * @throws \RuntimeException Si la classe du repository n'existe pas ou n'étend pas EntityRepository
     * 
     * @example
     * ```php
     * // Au lieu de :
     * $repo = new PizzaRepository($em, Pizza::class);
     * // Dans PizzaRepository::__construct() :
     * parent::__construct($em->getConnection(), new MetadataReader(), $entityClass);
     * 
     * // Utilisez :
     * $repo = $em->createRepository(PizzaRepository::class, Pizza::class);
     * // Le MetadataReader sera automatiquement partagé
     * ```
     */
    public function createRepository(string $repositoryClass, string $entityClass): RepositoryInterface
    {
        if (!class_exists($repositoryClass)) {
            throw new \RuntimeException("La classe de repository {$repositoryClass} n'existe pas.");
        }

        if (!is_subclass_of($repositoryClass, EntityRepository::class)) {
            throw new \RuntimeException(
                "La classe de repository {$repositoryClass} doit étendre " . EntityRepository::class . "."
            );
        }

        // Vérifier si le repository a un constructeur qui accepte EntityManager
        $reflection = new \ReflectionClass($repositoryClass);
        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            throw new \RuntimeException(
                "Le repository {$repositoryClass} doit avoir un constructeur qui accepte EntityManager et string."
            );
        }

        $parameters = $constructor->getParameters();
        
        // Si le constructeur accepte EntityManager en premier paramètre, l'utiliser
        if (count($parameters) >= 1 && $parameters[0]->getType()?->getName() === self::class) {
            return new $repositoryClass($this, $entityClass);
        }

        // Sinon, créer avec Connection et MetadataReader (ancienne méthode)
        return new $repositoryClass($this->connection, $this->metadataReader, $entityClass);
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
     * Retourne le lecteur de métadonnées
     */
    public function getMetadataReader(): MetadataReader
    {
        return $this->metadataReader;
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

