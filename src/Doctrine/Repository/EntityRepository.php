<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Repository;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Metadata\MetadataReader;
use JulienLinard\Doctrine\Cache\QueryCache;
use JulienLinard\Doctrine\LazyLoader\LazyCollection;
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
    public function find(int|string $id, bool $useCache = false, ?int $cacheTtl = null): ?object
    {
        if ($this->idProperty === null) {
            throw new \RuntimeException("L'entité {$this->entityClass} n'a pas de propriété ID définie.");
        }

        $metadata = $this->metadataReader->getMetadata($this->entityClass);
        $idColumn = $metadata['columns'][$this->idProperty]['name'] ?? $this->idProperty;

        $tableName = $this->escapeIdentifier($this->tableName);
        $idColumnEscaped = $this->escapeIdentifier($idColumn);
        $sql = "SELECT * FROM {$tableName} WHERE {$idColumnEscaped} = :id";
        $params = ['id' => $id];
        
        // Vérifier le cache si activé
        if ($useCache && $this->queryCache !== null && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($sql, $params);
            $cached = $this->queryCache->get($cacheKey);
            
            if ($cached !== null) {
                return $this->hydrate($cached);
            }
        }
        
        $row = $this->connection->fetchOne($sql, $params);

        if ($row === null) {
            return null;
        }

        $entity = $this->hydrate($row);
        
        // Mettre en cache si activé
        if ($useCache && $this->queryCache !== null && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($sql, $params);
            // ✅ PHASE 3.2: Tagger avec l'entité spécifique pour invalidation granulaire
            $tags = [$this->buildEntityTag($id)]; // find() concerne une entité spécifique
            $this->queryCache->set($cacheKey, $row, $cacheTtl, $tags);
        }
        
        return $entity;
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
            // ✅ PHASE 3.2: Tagger avec l'entité concernée pour invalidation granulaire
            $tags = [$this->buildEntityTag('*')]; // findAll() concerne toutes les entités de cette classe
            $this->queryCache->set($cacheKey, $rows, $cacheTtl, $tags);
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
            // ✅ PHASE 3.2: Tagger avec l'entité concernée pour invalidation granulaire
            $tags = [$this->buildEntityTag('*')]; // findBy() concerne toutes les entités de cette classe
            $this->queryCache->set($cacheKey, $cacheData, $cacheTtl, $tags);
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
     * Trouve une entité par ID ou lève une exception si non trouvée
     * 
     * @param int|string $id Identifiant de l'entité
     * @return object Entité trouvée
     * @throws \JulienLinard\Doctrine\Exceptions\EntityNotFoundException Si l'entité n'est pas trouvée
     */
    public function findOrFail(int|string $id): object
    {
        $entity = $this->find($id);
        
        if ($entity === null) {
            throw new \JulienLinard\Doctrine\Exceptions\EntityNotFoundException($this->entityClass, $id);
        }
        
        return $entity;
    }

    /**
     * Trouve une entité par critères ou lève une exception si non trouvée
     * 
     * @param array $criteria Critères de recherche
     * @return object Entité trouvée
     * @throws \JulienLinard\Doctrine\Exceptions\DoctrineException Si l'entité n'est pas trouvée
     */
    public function findOneByOrFail(array $criteria): object
    {
        $entity = $this->findOneBy($criteria);
        
        if ($entity === null) {
            $criteriaStr = json_encode($criteria, JSON_UNESCAPED_UNICODE);
            throw new \JulienLinard\Doctrine\Exceptions\DoctrineException(
                "L'entité {$this->entityClass} avec les critères {$criteriaStr} n'a pas été trouvée."
            );
        }
        
        return $entity;
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
                $property->setValue($entity, $value);
            }
        }

        // Charger les relations ManyToOne si elles existent
        $this->loadManyToOneRelations($entity, $row);
        
        // ✅ PHASE 3.1: Lazy loading automatique pour OneToMany
        // Initialiser les relations OneToMany avec LazyCollection si l'entité supporte le lazy loading
        if ($this->supportsLazyLoading($entity)) {
            $this->configureLazyLoading($entity);
            $this->initializeLazyRelations($entity);
        }
        
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
        $entityId = $idProperty->getValue($entity);
        
        if ($entityId === null) {
            return;
        }
        
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            if ($relation['type'] !== 'OneToMany') {
                continue;
            }
            
            $property = $reflection->getProperty($propertyName);
            $currentValue = $property->getValue($entity);
            
            // ✅ PHASE 3.1: Si c'est déjà une LazyCollection, ne pas la remplacer (elle se chargera elle-même)
            if ($currentValue instanceof LazyCollection) {
                continue;
            }
            
            // Si c'est déjà un tableau non vide, ne pas le remplacer
            if (is_array($currentValue) && !empty($currentValue)) {
                continue;
            }
            
            // Charger les entités liées
            $targetRepository = new EntityRepository(
                $this->connection,
                $this->metadataReader,
                $relation['targetEntity'],
                $this->queryCache
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
            $property->setValue($entity, $relatedEntities);
        }
    }
    
    /**
     * Trouve toutes les entités avec leurs relations chargées (eager loading)
     * Optimisé pour éviter les requêtes N+1 en utilisant des requêtes batch avec IN()
     * 
     * @param array $relations Liste des relations à charger (ex: ['posts', 'comments'])
     * @return array Tableau d'entités avec relations chargées
     */
    public function findAllWith(array $relations = []): array
    {
        $entities = $this->findAll();
        
        if (empty($entities) || empty($relations)) {
            return $entities;
        }
        
        // Optimisation N+1 : charger toutes les relations en batch
        $this->loadOneToManyRelationsBatch($entities, $relations);
        
        return $entities;
    }
    
    /**
     * Charge les relations OneToMany pour plusieurs entités en une seule requête (optimisation N+1)
     * 
     * @param array $entities Tableau d'entités pour lesquelles charger les relations
     * @param array $relationNames Liste des noms de relations à charger
     * @return void
     */
    private function loadOneToManyRelationsBatch(array $entities, array $relationNames): void
    {
        if (empty($entities)) {
            return;
        }
        
        $metadata = $this->metadataReader->getMetadata($this->entityClass);
        $reflection = new ReflectionClass($this->entityClass);
        
        // Collecter tous les IDs des entités
        $entityIds = [];
        $entityMap = []; // Map ID => entité pour assignation rapide
        
        $idProperty = $reflection->getProperty($metadata['id']);
        foreach ($entities as $entity) {
            $entityId = $idProperty->getValue($entity);
            if ($entityId !== null) {
                $entityIds[] = $entityId;
                $entityMap[$entityId] = $entity;
            }
        }
        
        if (empty($entityIds)) {
            return;
        }
        
        // Pour chaque relation demandée, charger toutes les entités liées en une seule requête
        foreach ($relationNames as $relationName) {
            if (!isset($metadata['relations'][$relationName])) {
                continue;
            }
            
                    $relation = $metadata['relations'][$relationName];
            if ($relation['type'] !== 'OneToMany') {
                continue;
            }
            
            // Créer le repository pour l'entité cible
            $targetRepository = new EntityRepository(
                $this->connection,
                $this->metadataReader,
                $relation['targetEntity'],
                $this->queryCache
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
            
            // OPTIMISATION : Une seule requête avec IN() pour charger toutes les relations
            $targetTable = $this->metadataReader->getTableName($relation['targetEntity']);
            $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
            $sql = "SELECT * FROM {$targetTable} WHERE {$joinColumn} IN ({$placeholders})";
            
            $rows = $this->connection->fetchAll($sql, $entityIds);
            
            // Grouper les résultats par ID parent
            $groupedResults = [];
            foreach ($rows as $row) {
                $parentId = $row[$joinColumn];
                if (!isset($groupedResults[$parentId])) {
                    $groupedResults[$parentId] = [];
                }
                $groupedResults[$parentId][] = $row;
            }
            
            // Hydrater les entités liées et les assigner aux entités parentes
            $property = $reflection->getProperty($relationName);
            foreach ($entityMap as $entityId => $entity) {
                $relatedRows = $groupedResults[$entityId] ?? [];
                $relatedEntities = array_map(
                    fn($row) => $targetRepository->hydrate($row),
                    $relatedRows
                );
                $property->setValue($entity, $relatedEntities);
            }
        }
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
        $entities = array_map([$this, 'hydrate'], $cachedData);
        
        // ✅ PHASE 3.1: Configurer le lazy loading pour toutes les entités
        foreach ($entities as $entity) {
            if ($this->supportsLazyLoading($entity)) {
                $this->configureLazyLoading($entity);
            }
        }
        
        return $entities;
    }
    
    /**
     * Vérifie si une entité supporte le lazy loading automatique
     * 
     * @param object $entity Entité
     * @return bool True si l'entité utilise LazyLoaderTrait
     */
    private function supportsLazyLoading(object $entity): bool
    {
        $className = get_class($entity);
        $traitName = \JulienLinard\Doctrine\LazyLoader\LazyLoaderTrait::class;
        
        // Vérifier les traits de la classe et de ses parents
        $classes = [$className];
        while ($parent = get_parent_class($className)) {
            $classes[] = $parent;
            $className = $parent;
        }
        
        foreach ($classes as $class) {
            $traits = class_uses($class);
            if ($traits && in_array($traitName, $traits, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Configure le lazy loading automatique pour une entité
     * 
     * @param object $entity Entité
     * @return void
     */
    private function configureLazyLoading(object $entity): void
    {
        if (method_exists($entity, '_setRepository')) {
            $entity->_setRepository($this);
        }
    }
    
    /**
     * Initialise les relations OneToMany avec LazyCollection pour le lazy loading automatique
     * 
     * @param object $entity Entité
     * @return void
     */
    private function initializeLazyRelations(object $entity): void
    {
        $metadata = $this->metadataReader->getMetadata($this->entityClass);
        $reflection = new ReflectionClass($this->entityClass);
        
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            if ($relation['type'] !== 'OneToMany') {
                continue;
            }
            
            // Vérifier si la propriété existe et n'est pas déjà initialisée
            if (!$reflection->hasProperty($propertyName)) {
                continue;
            }
            
            $property = $reflection->getProperty($propertyName);
            $currentValue = $property->getValue($entity);
            
            // Si la propriété est déjà un tableau non vide (chargé manuellement), ne pas la remplacer
            // Mais si c'est un tableau vide (initialisation par défaut), le remplacer par LazyCollection
            if (is_array($currentValue) && !empty($currentValue)) {
                continue;
            }
            
            // Si c'est déjà une LazyCollection, ne pas la remplacer
            if ($currentValue instanceof LazyCollection) {
                continue;
            }
            
            // Créer une LazyCollection qui chargera les relations à la demande
            $repository = $this;
            $entityClass = $this->entityClass;
            $relationData = $relation; // Capturer les données de la relation
            $lazyCollection = new LazyCollection(function() use ($repository, $entity, $propertyName, $entityClass, $relationData) {
                // Charger directement les entités liées sans passer par loadOneToManyRelations
                $metadata = $repository->metadataReader->getMetadata($entityClass);
                $reflection = new ReflectionClass($entityClass);
                
                // Récupérer l'ID de l'entité
                $idProperty = $reflection->getProperty($metadata['id']);
                $entityId = $idProperty->getValue($entity);
                
                if ($entityId === null) {
                    return [];
                }
                
                // Charger les entités liées
                $targetRepository = new EntityRepository(
                    $repository->connection,
                    $repository->metadataReader,
                    $relationData['targetEntity'],
                    $repository->queryCache
                );
                
                $targetMetadata = $repository->metadataReader->getMetadata($relationData['targetEntity']);
                $mappedBy = $relationData['mappedBy'];
                
                // Trouver la colonne de jointure
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
                return $targetRepository->findBy([$joinColumn => $entityId]);
            });
            
            $property->setValue($entity, $lazyCollection);
        }
    }
    
    /**
     * Construit un tag d'entité pour l'invalidation granulaire du cache
     * 
     * @param int|string $entityId ID de l'entité ('*' pour toutes les entités)
     * @return string Tag (ex: 'User:1' ou 'User:*')
     */
    private function buildEntityTag(int|string $entityId): string
    {
        $id = ($entityId === '*') ? '*' : (string)$entityId;
        return $this->entityClass . ':' . $id;
    }
}
