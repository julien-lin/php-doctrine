<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Repository;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Metadata\MetadataReader;
use JulienLinard\Doctrine\Cache\QueryCache;
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
    protected ?QueryCache $queryCache = null;

    /**
     * Constructeur
     *
     * @param Connection $connection Connexion à la base de données
     * @param MetadataReader $metadataReader Lecteur de métadonnées
     * @param string $entityClass Classe de l'entité
     * @param QueryCache|null $queryCache Cache de requêtes (optionnel)
     */
    public function __construct(
        Connection $connection,
        MetadataReader $metadataReader,
        string $entityClass,
        ?QueryCache $queryCache = null
    ) {
        $this->connection = $connection;
        $this->metadataReader = $metadataReader;
        $this->entityClass = $entityClass;
        $this->tableName = $metadataReader->getTableName($entityClass);
        $this->idProperty = $metadataReader->getIdProperty($entityClass);
        $this->queryCache = $queryCache;
    }
    
    /**
     * Définit le cache de requêtes
     * 
     * @param QueryCache|null $queryCache Cache de requêtes
     * @return void
     */
    public function setQueryCache(?QueryCache $queryCache): void
    {
        $this->queryCache = $queryCache;
    }
    
    /**
     * Retourne le cache de requêtes
     * 
     * @return QueryCache|null Cache de requêtes
     */
    public function getQueryCache(): ?QueryCache
    {
        return $this->queryCache;
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
     * 
     * @param bool $useCache Utiliser le cache (défaut: false)
     * @param int|null $cacheTtl TTL du cache en secondes (null = TTL par défaut)
     * @return array Tableau d'entités
     */
    public function findAll(bool $useCache = false, ?int $cacheTtl = null): array
    {
        $tableName = $this->escapeIdentifier($this->tableName);
        $sql = "SELECT * FROM {$tableName}";
        $params = [];
        
        // Vérifier le cache si activé
        if ($useCache && $this->queryCache !== null && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($sql, $params);
            $cached = $this->queryCache->get($cacheKey);
            
            if ($cached !== null) {
                return $this->hydrateFromCache($cached);
            }
        }
        
        $rows = $this->connection->fetchAll($sql, $params);
        $entities = array_map([$this, 'hydrate'], $rows);
        
        // Mettre en cache si activé
        if ($useCache && $this->queryCache !== null && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($sql, $params);
            $this->queryCache->set($cacheKey, $rows, $cacheTtl);
        }
        
        return $entities;
    }

    /**
     * Trouve des entités par critères
     * 
     * @param array $criteria Critères de recherche
     * @param array|null $orderBy Tri (ex: ['name' => 'ASC'])
     * @param int|null $limit Limite
     * @param int|null $offset Offset
     * @param bool $useCache Utiliser le cache (défaut: false)
     * @param int|null $cacheTtl TTL du cache en secondes (null = TTL par défaut)
     * @return array Tableau d'entités
     */
    public function findBy(
        array $criteria, 
        ?array $orderBy = null, 
        ?int $limit = null, 
        ?int $offset = null,
        bool $useCache = false,
        ?int $cacheTtl = null
    ): array {
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

        // Vérifier le cache si activé
        if ($useCache && $this->queryCache !== null && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($sql, $params);
            $cached = $this->queryCache->get($cacheKey);
            
            if ($cached !== null) {
                // Désérialiser les entités depuis le cache
                return $this->hydrateFromCache($cached);
            }
        }

        $rows = $this->connection->fetchAll($sql, $params);
        $entities = array_map([$this, 'hydrate'], $rows);
        
        // Mettre en cache si activé
        if ($useCache && $this->queryCache !== null && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($sql, $params);
            // Sérialiser les données brutes pour le cache (pas les objets)
            $cacheData = $rows; // Stocker les données brutes plutôt que les objets
            $this->queryCache->set($cacheKey, $cacheData, $cacheTtl);
        }
        
        return $entities;
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

        // Charger les relations ManyToOne si elles existent
        $this->loadManyToOneRelations($entity, $row);
        
        return $entity;
    }
    
    /**
     * Charge les relations ManyToOne d'une entité
     * 
     * @param object $entity Entité
     * @param array $row Données de la base
     */
    private function loadManyToOneRelations(object $entity, array $row): void
    {
        $metadata = $this->metadataReader->getMetadata($this->entityClass);
        $reflection = new ReflectionClass($this->entityClass);
        
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            if ($relation['type'] !== 'ManyToOne') {
                continue;
            }
            
            $joinColumn = $relation['joinColumn'];
            if (!isset($row[$joinColumn]) || $row[$joinColumn] === null) {
                continue;
            }
            
            // Charger l'entité liée
            $targetRepository = new EntityRepository(
                $this->connection,
                $this->metadataReader,
                $relation['targetEntity']
            );
            
            $relatedEntity = $targetRepository->find($row[$joinColumn]);
            
            if ($relatedEntity !== null) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($entity, $relatedEntity);
            }
        }
    }
    
    /**
     * Charge les relations OneToMany d'une entité
     * 
     * @param object $entity Entité
     * @return void
     */
    public function loadOneToManyRelations(object $entity): void
    {
        $metadata = $this->metadataReader->getMetadata($this->entityClass);
        $reflection = new ReflectionClass($this->entityClass);
        
        // Récupérer l'ID de l'entité
        $idProperty = $reflection->getProperty($metadata['id']);
        $idProperty->setAccessible(true);
        $entityId = $idProperty->getValue($entity);
        
        if ($entityId === null) {
            return;
        }
        
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            if ($relation['type'] !== 'OneToMany') {
                continue;
            }
            
            // Charger les entités liées
            $targetRepository = new EntityRepository(
                $this->connection,
                $this->metadataReader,
                $relation['targetEntity']
            );
            
            $targetMetadata = $this->metadataReader->getMetadata($relation['targetEntity']);
            $mappedBy = $relation['mappedBy'];
            
            // Trouver la colonne de jointure dans l'entité cible
            $joinColumn = null;
            foreach ($targetMetadata['relations'] ?? [] as $targetProp => $targetRel) {
                if ($targetRel['type'] === 'ManyToOne' && $targetRel['joinColumn']) {
                    $joinColumn = $targetRel['joinColumn'];
                    break;
                }
            }
            
            if ($joinColumn === null) {
                $joinColumn = $mappedBy . '_id';
            }
            
            // Rechercher les entités liées
            $relatedEntities = $targetRepository->findBy([$joinColumn => $entityId]);
            
            // Définir la propriété
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($entity, $relatedEntities);
        }
    }
    
    /**
     * Trouve toutes les entités avec leurs relations chargées (eager loading)
     * 
     * @param array $relations Liste des relations à charger (ex: ['posts', 'comments'])
     * @return array Tableau d'entités avec relations chargées
     */
    public function findAllWith(array $relations = []): array
    {
        $entities = $this->findAll();
        
        foreach ($entities as $entity) {
            // Charger les relations OneToMany demandées
            foreach ($relations as $relationName) {
                $metadata = $this->metadataReader->getMetadata($this->entityClass);
                if (isset($metadata['relations'][$relationName])) {
                    $relation = $metadata['relations'][$relationName];
                    if ($relation['type'] === 'OneToMany') {
                        $this->loadOneToManyRelations($entity);
                    }
                }
            }
        }
        
        return $entities;
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
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }
    
    /**
     * Hydrate des entités depuis les données du cache
     * 
     * @param array $cachedData Données en cache (tableau de tableaux)
     * @return array Tableau d'entités
     */
    private function hydrateFromCache(array $cachedData): array
    {
        return array_map([$this, 'hydrate'], $cachedData);
    }
}

