<?php

declare(strict_types=1);

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
     * Cache des instances ReflectionClass
     */
    private array $reflectionCache = [];
    
    /**
     * État original des entités chargées (pour dirty checking)
     * Clé : spl_object_hash($entity), Valeur : tableau des valeurs originales
     */
    private array $originalStates = [];

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
        
        // Si l'entité a un ID, sauvegarder son état original pour le dirty checking
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        
        if ($metadata['id'] !== null) {
            $reflection = $this->getReflectionClass($entity);
            $idProperty = $reflection->getProperty($metadata['id']);
            $idProperty->setAccessible(true);
            $id = $idProperty->getValue($entity);
            
            // Si l'entité a un ID, sauvegarder l'état original
            if ($id !== null && $id !== 0 && !isset($this->originalStates[spl_object_hash($entity)])) {
                $this->originalStates[spl_object_hash($entity)] = $this->getEntityState($entity);
            }
        }
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
        // Gérer les cascades persist avant de persister les entités principales
        $this->processCascadePersist();
        
        // Trier les entités à persister : d'abord celles sans relations ManyToOne, puis les autres
        $entitiesToPersist = $this->toPersist;
        $processed = [];
        
        while (!empty($entitiesToPersist)) {
            $progressMade = false;
            
            foreach ($entitiesToPersist as $key => $entity) {
                $entityHash = spl_object_hash($entity);
                if (isset($processed[$entityHash])) {
                    unset($entitiesToPersist[$key]);
                    continue;
                }
                
                // Vérifier si toutes les entités ManyToOne liées sont déjà persistées
                $className = get_class($entity);
                $metadata = $this->metadataReader->getMetadata($className);
                $canPersist = true;
                
                foreach ($metadata['relations'] ?? [] as $relation) {
                    if ($relation['type'] === 'ManyToOne') {
                        // Vérifier si l'entité liée est dans toPersist et non encore persistée
                        // Pour simplifier, on persiste toujours (les relations seront gérées dans insertEntity)
                    }
                }
                
                if ($canPersist) {
                    $this->persistEntity($entity);
                    $processed[$entityHash] = true;
                    unset($entitiesToPersist[$key]);
                    $progressMade = true;
                }
            }
            
            // Si aucun progrès n'a été fait, forcer la persistance (pour éviter les boucles infinies)
            if (!$progressMade && !empty($entitiesToPersist)) {
                foreach ($entitiesToPersist as $entity) {
                    $this->persistEntity($entity);
                }
                break;
            }
        }
        
        $this->toPersist = [];

        // Gérer les cascades remove avant de supprimer les entités principales
        $this->processCascadeRemove();

        // Supprimer les entités marquées
        foreach ($this->toRemove as $entity) {
            $this->deleteEntity($entity);
            // Nettoyer l'état original après suppression
            unset($this->originalStates[spl_object_hash($entity)]);
        }
        $this->toRemove = [];
    }
    
    /**
     * Persiste une entité (insertion ou mise à jour)
     */
    private function persistEntity(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        
        // Vérifier si l'entité a un ID (mise à jour) ou non (insertion)
        if ($metadata['id'] !== null) {
            $reflection = $this->getReflectionClass($entity);
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
    
    /**
     * Traite les cascades persist
     */
    private function processCascadePersist(): void
    {
        $processed = [];
        
        foreach ($this->toPersist as $entity) {
            $this->processCascadePersistForEntity($entity, $processed);
        }
    }
    
    /**
     * Traite les cascades persist pour une entité
     */
    private function processCascadePersistForEntity(object $entity, array &$processed): void
    {
        $entityHash = spl_object_hash($entity);
        if (isset($processed[$entityHash])) {
            return;
        }
        $processed[$entityHash] = true;
        
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $reflection = $this->getReflectionClass($entity);
        
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            if (!in_array('persist', $relation['cascade'] ?? [], true)) {
                continue;
            }
            
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            if ($value === null) {
                continue;
            }
            
            if ($relation['type'] === 'OneToMany' || $relation['type'] === 'ManyToMany') {
                // Collection d'entités
                if (is_array($value)) {
                    foreach ($value as $relatedEntity) {
                        if (is_object($relatedEntity)) {
                            $this->toPersist[] = $relatedEntity;
                            $this->processCascadePersistForEntity($relatedEntity, $processed);
                        }
                    }
                }
            } else {
                // Entité unique
                if (is_object($value)) {
                    $this->toPersist[] = $value;
                    $this->processCascadePersistForEntity($value, $processed);
                }
            }
        }
    }
    
    /**
     * Traite les cascades remove
     */
    private function processCascadeRemove(): void
    {
        $processed = [];
        
        foreach ($this->toRemove as $entity) {
            $this->processCascadeRemoveForEntity($entity, $processed);
        }
    }
    
    /**
     * Traite les cascades remove pour une entité
     */
    private function processCascadeRemoveForEntity(object $entity, array &$processed): void
    {
        $entityHash = spl_object_hash($entity);
        if (isset($processed[$entityHash])) {
            return;
        }
        $processed[$entityHash] = true;
        
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $reflection = $this->getReflectionClass($entity);
        
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            if (!in_array('remove', $relation['cascade'] ?? [], true)) {
                continue;
            }
            
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            if ($value === null) {
                continue;
            }
            
            if ($relation['type'] === 'OneToMany' || $relation['type'] === 'ManyToMany') {
                // Collection d'entités
                if (is_array($value)) {
                    foreach ($value as $relatedEntity) {
                        if (is_object($relatedEntity)) {
                            $this->toRemove[] = $relatedEntity;
                            $this->processCascadeRemoveForEntity($relatedEntity, $processed);
                        }
                    }
                }
            } else {
                // Entité unique
                if (is_object($value)) {
                    $this->toRemove[] = $value;
                    $this->processCascadeRemoveForEntity($value, $processed);
                }
            }
        }
    }

    /**
     * Récupère ou crée une instance ReflectionClass pour une classe
     * 
     * @param string|object $class Classe ou objet
     * @return \ReflectionClass Instance de ReflectionClass
     */
    private function getReflectionClass(string|object $class): \ReflectionClass
    {
        $className = is_object($class) ? get_class($class) : $class;
        
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = new \ReflectionClass($className);
        }
        
        return $this->reflectionCache[$className];
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
        
        $reflection = $this->getReflectionClass($entity);
        
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
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
        
        // Gérer les relations ManyToOne : extraire l'ID de l'entité liée
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            if ($relation['type'] !== 'ManyToOne') {
                continue;
            }
            
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $relatedEntity = $property->getValue($entity);
            
            if ($relatedEntity !== null && is_object($relatedEntity)) {
                // Extraire l'ID de l'entité liée
                $relatedMetadata = $this->metadataReader->getMetadata($relation['targetEntity']);
                $relatedReflection = $this->getReflectionClass($relatedEntity);
                $relatedIdProperty = $relatedReflection->getProperty($relatedMetadata['id']);
                $relatedIdProperty->setAccessible(true);
                $relatedId = $relatedIdProperty->getValue($relatedEntity);
                
                // Si l'entité liée n'a pas d'ID, la persister d'abord
                if ($relatedId === null || $relatedId === 0) {
                    $this->insertEntity($relatedEntity);
                    $relatedId = $relatedIdProperty->getValue($relatedEntity);
                }
                
                if ($relatedId !== null) {
                    $joinColumn = $relation['joinColumn'];
                    $joinColumnEscaped = $this->escapeIdentifier($joinColumn);
                    
                    // Vérifier si la colonne n'existe pas déjà dans les colonnes
                    if (!in_array($joinColumnEscaped, $columns)) {
                        $columns[] = $joinColumnEscaped;
                        $values[] = ":{$joinColumn}";
                        $params[$joinColumn] = $relatedId;
                    } else {
                        // Mettre à jour la valeur existante
                        $params[$joinColumn] = $relatedId;
                    }
                }
            }
        }
        
        $sql = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        $this->connection->execute($sql, $params);
        
        // Récupérer l'ID généré
        if ($metadata['id'] !== null) {
            $idColumn = $metadata['columns'][$metadata['id']]['name'] ?? $metadata['id'];
            $lastId = $this->connection->lastInsertId();
            if ($lastId) {
                $idProperty = $reflection->getProperty($metadata['id']);
                $idProperty->setAccessible(true);
                $idProperty->setValue($entity, (int)$lastId);
            }
        }
    }

    /**
     * Met à jour une entité en base de données
     * Utilise le dirty checking pour ne mettre à jour que les propriétés modifiées
     */
    private function updateEntity(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $tableName = $this->escapeIdentifier($metadata['table']);
        
        $sets = [];
        $params = [];
        
        // Récupérer l'ID pour la clause WHERE
        $reflection = $this->getReflectionClass($entity);
        $idPropertyName = $metadata['id'];
        $idProperty = $reflection->getProperty($idPropertyName);
        $idProperty->setAccessible(true);
        $idValue = $idProperty->getValue($entity);
        
        if ($idValue === null) {
            throw new \RuntimeException("Impossible de mettre à jour une entité sans ID");
        }
        
        $idColumn = $metadata['columns'][$idPropertyName]['name'] ?? $idPropertyName;
        
        // Récupérer l'état original pour le dirty checking
        $entityHash = spl_object_hash($entity);
        $originalState = $this->originalStates[$entityHash] ?? null;
        $currentState = $this->getEntityState($entity);
        
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
            
            // Dirty checking : ne mettre à jour que si la valeur a changé
            if ($originalState !== null) {
                $originalValue = $originalState[$propertyName] ?? null;
                $currentValue = $currentState[$propertyName] ?? null;
                
                // Comparer les valeurs (en tenant compte des types)
                if ($this->valuesAreEqual($originalValue, $currentValue, $columnInfo['type'])) {
                    continue; // La valeur n'a pas changé, ne pas l'inclure dans l'UPDATE
                }
            }
            
            $columnName = $columnInfo['name'];
            $sets[] = $this->escapeIdentifier($columnName) . " = :{$columnName}";
            $params[$columnName] = $this->convertToDatabaseValue($value, $columnInfo['type']);
        }
        
        // Si aucune propriété n'a changé, ne pas exécuter l'UPDATE
        if (empty($sets)) {
            return;
        }
        
        // Ajouter l'ID dans les paramètres pour la clause WHERE
        $params['id'] = $idValue;
        $idColumnEscaped = $this->escapeIdentifier($idColumn);
        
        $sql = "UPDATE {$tableName} SET " . implode(', ', $sets) . " WHERE {$idColumnEscaped} = :id";
        $this->connection->execute($sql, $params);
        
        // Mettre à jour l'état original après la mise à jour
        $this->originalStates[$entityHash] = $currentState;
    }
    
    /**
     * Récupère l'état actuel d'une entité (toutes les valeurs des propriétés)
     * 
     * @param object $entity Entité
     * @return array État de l'entité [propertyName => value]
     */
    private function getEntityState(object $entity): array
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $reflection = $this->getReflectionClass($entity);
        $state = [];
        
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            // Normaliser les valeurs pour la comparaison
            $state[$propertyName] = $this->normalizeValueForComparison($value, $columnInfo['type']);
        }
        
        return $state;
    }
    
    /**
     * Normalise une valeur pour la comparaison
     * 
     * @param mixed $value Valeur à normaliser
     * @param string $type Type de la colonne
     * @return mixed Valeur normalisée
     */
    private function normalizeValueForComparison(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        
        return match ($type) {
            'boolean', 'bool' => (bool)$value,
            'integer', 'int' => (int)$value,
            'float', 'double' => (float)$value,
            'datetime' => $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : (string)$value,
            'date' => $value instanceof \DateTime ? $value->format('Y-m-d') : (string)$value,
            'time' => $value instanceof \DateTime ? $value->format('H:i:s') : (string)$value,
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string)$value,
        };
    }
    
    /**
     * Compare deux valeurs pour déterminer si elles sont égales
     * 
     * @param mixed $value1 Première valeur
     * @param mixed $value2 Deuxième valeur
     * @param string $type Type de la colonne
     * @return bool True si les valeurs sont égales
     */
    private function valuesAreEqual(mixed $value1, mixed $value2, string $type): bool
    {
        // Les deux sont null
        if ($value1 === null && $value2 === null) {
            return true;
        }
        
        // L'un est null et l'autre non
        if ($value1 === null || $value2 === null) {
            return false;
        }
        
        // Normaliser les valeurs pour la comparaison
        $normalized1 = $this->normalizeValueForComparison($value1, $type);
        $normalized2 = $this->normalizeValueForComparison($value2, $type);
        
        // Comparaison stricte
        return $normalized1 === $normalized2;
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
        
        $reflection = $this->getReflectionClass($entity);
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
        $entity = $this->getRepository($entityClass)->find($id);
        
        // Enregistrer l'état original pour le dirty checking
        if ($entity !== null) {
            $this->registerOriginalState($entity);
        }
        
        return $entity;
    }
    
    /**
     * Charge les relations OneToMany d'une entité (lazy loading)
     * 
     * @param object $entity Entité
     * @param string|null $relationName Nom de la relation à charger (null = toutes)
     */
    public function loadRelations(object $entity, ?string $relationName = null): void
    {
        $className = get_class($entity);
        $repository = $this->getRepository($className);
        
        if ($repository instanceof EntityRepository) {
            if ($relationName !== null) {
                // Charger une relation spécifique
                $metadata = $this->metadataReader->getMetadata($className);
                if (isset($metadata['relations'][$relationName])) {
                    $relation = $metadata['relations'][$relationName];
                    if ($relation['type'] === 'OneToMany') {
                        $repository->loadOneToManyRelations($entity);
                    }
                }
            } else {
                // Charger toutes les relations OneToMany
                $repository->loadOneToManyRelations($entity);
            }
        }
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
            'json' => is_array($value) || is_object($value) ? json_encode($value) : $value,
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
     * Enregistre l'état original d'une entité chargée depuis la base de données
     * Cette méthode est utilisée par les repositories pour activer le dirty checking
     * 
     * @param object $entity Entité chargée
     */
    public function registerOriginalState(object $entity): void
    {
        $entityHash = spl_object_hash($entity);
        if (!isset($this->originalStates[$entityHash])) {
            $this->originalStates[$entityHash] = $this->getEntityState($entity);
        }
    }
    
    /**
     * Vérifie si une entité a été modifiée (dirty checking)
     * 
     * @param object $entity Entité à vérifier
     * @return bool True si l'entité a été modifiée
     */
    public function isDirty(object $entity): bool
    {
        $entityHash = spl_object_hash($entity);
        
        // Si l'entité n'a pas d'état original, elle est considérée comme nouvelle (dirty)
        if (!isset($this->originalStates[$entityHash])) {
            return true;
        }
        
        $originalState = $this->originalStates[$entityHash];
        $currentState = $this->getEntityState($entity);
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        
        // Comparer chaque propriété
        foreach ($metadata['columns'] as $propertyName => $columnInfo) {
            $originalValue = $originalState[$propertyName] ?? null;
            $currentValue = $currentState[$propertyName] ?? null;
            
            if (!$this->valuesAreEqual($originalValue, $currentValue, $columnInfo['type'])) {
                return true; // L'entité a été modifiée
            }
        }
        
        return false; // L'entité n'a pas été modifiée
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

    /**
     * Génère une migration pour une entité
     * 
     * @param string $entityClass Classe de l'entité
     * @return string SQL de migration
     */
    public function generateMigration(string $entityClass): string
    {
        $generator = new \JulienLinard\Doctrine\Migration\MigrationGenerator(
            $this->connection,
            $this->metadataReader
        );
        
        return $generator->generateForEntity($entityClass);
    }
    
    /**
     * Génère des migrations pour plusieurs entités
     * 
     * @param array $entityClasses Tableau de classes d'entités
     * @return string SQL combiné
     */
    public function generateMigrations(array $entityClasses): string
    {
        $generator = new \JulienLinard\Doctrine\Migration\MigrationGenerator(
            $this->connection,
            $this->metadataReader
        );
        
        return $generator->generateForEntities($entityClasses);
    }
    
    /**
     * Retourne le MigrationManager
     * 
     * @return \JulienLinard\Doctrine\Migration\MigrationManager
     */
    public function getMigrationManager(): \JulienLinard\Doctrine\Migration\MigrationManager
    {
        return new \JulienLinard\Doctrine\Migration\MigrationManager($this->connection);
    }
    
    /**
     * Retourne le MigrationRunner
     * 
     * @return \JulienLinard\Doctrine\Migration\MigrationRunner
     */
    public function getMigrationRunner(): \JulienLinard\Doctrine\Migration\MigrationRunner
    {
        return new \JulienLinard\Doctrine\Migration\MigrationRunner($this->connection);
    }
}

