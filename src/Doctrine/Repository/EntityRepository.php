<?php

namespace JulienLinard\Doctrine\Repository;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Metadata\MetadataReader;
use ReflectionClass;

/**
 * Repository de base pour les entités
 */
class EntityRepository implements RepositoryInterface
{
    protected Connection $connection;
    protected MetadataReader $metadataReader;
    protected string $entityClass;
    protected string $tableName;
    protected ?string $idProperty;

    /**
     * Constructeur
     *
     * @param Connection $connection Connexion à la base de données
     * @param MetadataReader $metadataReader Lecteur de métadonnées
     * @param string $entityClass Classe de l'entité
     */
    public function __construct(
        Connection $connection,
        MetadataReader $metadataReader,
        string $entityClass
    ) {
        $this->connection = $connection;
        $this->metadataReader = $metadataReader;
        $this->entityClass = $entityClass;
        $this->tableName = $metadataReader->getTableName($entityClass);
        $this->idProperty = $metadataReader->getIdProperty($entityClass);
    }

    /**
     * Valide qu'un identifiant SQL est valide
     * 
     * @param string $identifier Identifiant à valider
     * @throws \InvalidArgumentException Si l'identifiant n'est pas valide
     */
    protected function validateIdentifier(string $identifier): void
    {
        // Un identifiant SQL valide commence par une lettre ou underscore
        // et contient uniquement des lettres, chiffres et underscores
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException(
                "Invalid identifier: '{$identifier}'. Identifiers must start with a letter or underscore and contain only letters, numbers, and underscores."
            );
        }
    }

    /**
     * Échappe un identifiant SQL (table ou colonne) avec des backticks
     * 
     * @param string $identifier Identifiant à échapper
     * @return string Identifiant échappé
     * @throws \InvalidArgumentException Si l'identifiant n'est pas valide
     */
    protected function escapeIdentifier(string $identifier): string
    {
        $this->validateIdentifier($identifier);
        return "`{$identifier}`";
    }

    /**
     * Trouve une entité par son ID
     */
    public function find(int|string $id): ?object
    {
        if ($this->idProperty === null) {
            throw new \RuntimeException("L'entité {$this->entityClass} n'a pas de propriété ID définie.");
        }

        $metadata = $this->metadataReader->getMetadata($this->entityClass);
        $idColumn = $metadata['columns'][$this->idProperty]['name'] ?? $this->idProperty;

        $tableName = $this->escapeIdentifier($this->tableName);
        $idColumnEscaped = $this->escapeIdentifier($idColumn);
        $sql = "SELECT * FROM {$tableName} WHERE {$idColumnEscaped} = :id";
        $row = $this->connection->fetchOne($sql, ['id' => $id]);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Trouve toutes les entités
     */
    public function findAll(): array
    {
        $tableName = $this->escapeIdentifier($this->tableName);
        $sql = "SELECT * FROM {$tableName}";
        $rows = $this->connection->fetchAll($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Trouve des entités par critères
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $tableName = $this->escapeIdentifier($this->tableName);
        $sql = "SELECT * FROM {$tableName}";
        $params = [];

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $fieldEscaped = $this->escapeIdentifier($field);
                $conditions[] = "{$fieldEscaped} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        if ($orderBy !== null) {
            $orders = [];
            foreach ($orderBy as $field => $direction) {
                // Valider le nom du champ
                $this->validateIdentifier($field);
                $fieldEscaped = $this->escapeIdentifier($field);
                
                // Valider la direction (ASC ou DESC)
                $direction = strtoupper($direction);
                if (!in_array($direction, ['ASC', 'DESC'], true)) {
                    throw new \InvalidArgumentException(
                        "Invalid order direction: '{$direction}'. Must be 'ASC' or 'DESC'."
                    );
                }
                
                $orders[] = "{$fieldEscaped} {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orders);
        }

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }

        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }

        $rows = $this->connection->fetchAll($sql, $params);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Trouve une entité par critères
     */
    public function findOneBy(array $criteria): ?object
    {
        $results = $this->findBy($criteria, null, 1);
        return $results[0] ?? null;
    }

    /**
     * Hydrate une entité depuis un tableau de données
     *
     * @param array $row Données de la base
     * @return object Instance de l'entité
     */
    protected function hydrate(array $row): object
    {
        $metadata = $this->metadataReader->getMetadata($this->entityClass);
        $data = [];

        // Mapper les colonnes aux propriétés
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            $columnName = $columnInfo['name'];
            if (isset($row[$columnName])) {
                $value = $row[$columnName];
                
                // Convertir selon le type
                $value = $this->convertValue($value, $columnInfo['type']);
                
                $data[$propertyName] = $value;
            }
        }

        // Créer l'instance de l'entité
        $reflection = new ReflectionClass($this->entityClass);
        $entity = $reflection->newInstance();
        
        // Hydrater les propriétés
        foreach ($data as $propertyName => $value) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($entity, $value);
            }
        }

        return $entity;
    }

    /**
     * Convertit une valeur selon son type
     */
    private function convertValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer', 'int' => (int)$value,
            'boolean', 'bool' => (bool)$value,
            'float', 'double' => (float)$value,
            'datetime' => $value instanceof \DateTime ? $value : new \DateTime($value),
            'date' => $value instanceof \DateTime ? $value : \DateTime::createFromFormat('Y-m-d', $value),
            'time' => $value instanceof \DateTime ? $value : \DateTime::createFromFormat('H:i:s', $value),
            default => $value,
        };
    }
}

