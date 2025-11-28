<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Metadata;

use ReflectionClass;
use ReflectionProperty;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Index;
use JulienLinard\Doctrine\Mapping\ManyToOne;
use JulienLinard\Doctrine\Mapping\OneToMany;
use JulienLinard\Doctrine\Mapping\ManyToMany;

/**
 * Lecteur de métadonnées pour les entités
 */
class MetadataReader
{
    /**
     * Cache des métadonnées
     */
    private array $metadataCache = [];

    /**
     * Lit les métadonnées d'une classe d'entité
     *
     * @param string $className Nom de la classe
     * @return array Métadonnées de l'entité
     */
    public function getMetadata(string $className): array
    {
        if (isset($this->metadataCache[$className])) {
            return $this->metadataCache[$className];
        }

        $reflection = new ReflectionClass($className);
        $metadata = [
            'table' => null,
            'id' => null,
            'columns' => [],
            'relations' => [],
            'indexes' => [],
        ];

        // Lire l'attribut Entity
        $entityAttributes = $reflection->getAttributes(Entity::class);
        if (empty($entityAttributes)) {
            throw new \RuntimeException("La classe {$className} n'est pas une entité (manque l'attribut #[Entity]).");
        }

        $entityAttribute = $entityAttributes[0]->newInstance();
        $metadata['table'] = $entityAttribute->table;

        // Lire les propriétés
        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            // Vérifier si c'est un ID
            $idAttributes = $property->getAttributes(Id::class);
            if (!empty($idAttributes)) {
                $metadata['id'] = $propertyName;
            }

            // Lire les colonnes
            $columnAttributes = $property->getAttributes(Column::class);
            if (!empty($columnAttributes)) {
                $columnAttribute = $columnAttributes[0]->newInstance();
                $columnName = $columnAttribute->name ?? $propertyName;
                $metadata['columns'][$propertyName] = [
                    'type' => $columnAttribute->type,
                    'length' => $columnAttribute->length,
                    'nullable' => $columnAttribute->nullable,
                    'default' => $columnAttribute->default,
                    'name' => $columnName,
                    'autoIncrement' => $columnAttribute->autoIncrement,
                ];
                
                // Lire les index
                $indexAttributes = $property->getAttributes(Index::class);
                if (!empty($indexAttributes)) {
                    $indexAttribute = $indexAttributes[0]->newInstance();
                    $indexName = $indexAttribute->name ?? ('idx_' . $columnName);
                    $metadata['indexes'][] = [
                        'column' => $columnName,
                        'name' => $indexName,
                        'unique' => $indexAttribute->unique,
                    ];
                }
            }

            // Lire les relations ManyToOne
            $manyToOneAttributes = $property->getAttributes(ManyToOne::class);
            if (!empty($manyToOneAttributes)) {
                $manyToOneAttribute = $manyToOneAttributes[0]->newInstance();
                $metadata['relations'][$propertyName] = [
                    'type' => 'ManyToOne',
                    'targetEntity' => $manyToOneAttribute->targetEntity,
                    'inversedBy' => $manyToOneAttribute->inversedBy,
                    'joinColumn' => $manyToOneAttribute->joinColumn ?? $propertyName . '_id',
                    'cascade' => $manyToOneAttribute->cascade ?? [],
                ];
            }

            // Lire les relations OneToMany
            $oneToManyAttributes = $property->getAttributes(OneToMany::class);
            if (!empty($oneToManyAttributes)) {
                $oneToManyAttribute = $oneToManyAttributes[0]->newInstance();
                $metadata['relations'][$propertyName] = [
                    'type' => 'OneToMany',
                    'targetEntity' => $oneToManyAttribute->targetEntity,
                    'mappedBy' => $oneToManyAttribute->mappedBy,
                    'cascade' => $oneToManyAttribute->cascade ?? [],
                ];
            }

            // Lire les relations ManyToMany
            $manyToManyAttributes = $property->getAttributes(ManyToMany::class);
            if (!empty($manyToManyAttributes)) {
                $manyToManyAttribute = $manyToManyAttributes[0]->newInstance();
                $metadata['relations'][$propertyName] = [
                    'type' => 'ManyToMany',
                    'targetEntity' => $manyToManyAttribute->targetEntity,
                    'mappedBy' => $manyToManyAttribute->mappedBy,
                    'joinTable' => $manyToManyAttribute->joinTable,
                ];
            }
        }

        $this->metadataCache[$className] = $metadata;
        return $metadata;
    }

    /**
     * Retourne le nom de la table pour une classe d'entité
     */
    public function getTableName(string $className): string
    {
        $metadata = $this->getMetadata($className);
        return $metadata['table'];
    }

    /**
     * Retourne le nom de la propriété ID
     */
    public function getIdProperty(string $className): ?string
    {
        $metadata = $this->getMetadata($className);
        return $metadata['id'];
    }
}

