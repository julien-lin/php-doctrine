<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\QueryBuilder;

use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Metadata\MetadataReader;

/**
 * Query Builder pour construire des requêtes SQL de manière fluide
 */
class QueryBuilder
{
    private Connection $connection;
    private MetadataReader $metadataReader;
    private array $select = [];
    private array $from = [];
    private array $where = [];
    private array $join = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $parameters = [];

    /**
     * Constructeur
     */
    public function __construct(Connection $connection, MetadataReader $metadataReader)
    {
        $this->connection = $connection;
        $this->metadataReader = $metadataReader;
    }

    /**
     * Ajoute un SELECT
     *
     * @param string|array $fields Champs à sélectionner
     * @return self Instance pour chaînage
     */
    public function select(string|array $fields): self
    {
        if (is_array($fields)) {
            $this->select = array_merge($this->select, $fields);
        } else {
            $this->select[] = $fields;
        }
        return $this;
    }

    /**
     * Ajoute un FROM
     *
     * @param string $entityClass Classe de l'entité
     * @param string $alias Alias pour la table
     * @return self Instance pour chaînage
     */
    public function from(string $entityClass, string $alias): self
    {
        $this->validateIdentifier($alias);
        $tableName = $this->metadataReader->getTableName($entityClass);
        $this->from[] = [
            'entity' => $entityClass,
            'table' => $tableName,
            'alias' => $alias,
        ];
        return $this;
    }

    /**
     * Ajoute une condition WHERE
     *
     * @param string $condition Condition SQL
     * @param mixed $value Valeur (optionnel)
     * @return self Instance pour chaînage
     */
    public function where(string $condition, mixed $value = null): self
    {
        if ($value !== null) {
            $paramName = 'param_' . count($this->parameters);
            $this->where[] = str_replace('?', ':' . $paramName, $condition);
            $this->parameters[$paramName] = $value;
        } else {
            $this->where[] = $condition;
        }
        return $this;
    }

    /**
     * Ajoute une condition AND WHERE
     */
    public function andWhere(string $condition, mixed $value = null): self
    {
        if ($value !== null) {
            $paramName = 'param_' . count($this->parameters);
            $this->where[] = 'AND ' . str_replace('?', ':' . $paramName, $condition);
            $this->parameters[$paramName] = $value;
        } else {
            $this->where[] = 'AND ' . $condition;
        }
        return $this;
    }

    /**
     * Ajoute une condition OR WHERE
     */
    public function orWhere(string $condition, mixed $value = null): self
    {
        if ($value !== null) {
            $paramName = 'param_' . count($this->parameters);
            $this->where[] = 'OR ' . str_replace('?', ':' . $paramName, $condition);
            $this->parameters[$paramName] = $value;
        } else {
            $this->where[] = 'OR ' . $condition;
        }
        return $this;
    }

    /**
     * Ajoute un JOIN
     */
    public function join(string $entityClass, string $alias, string $condition, string $type = 'INNER'): self
    {
        $this->validateIdentifier($alias);
        $this->validateJoinType($type);
        $tableName = $this->metadataReader->getTableName($entityClass);
        $this->join[] = [
            'type' => $type,
            'table' => $tableName,
            'alias' => $alias,
            'condition' => $condition,
        ];
        return $this;
    }

    /**
     * Ajoute un LEFT JOIN
     */
    public function leftJoin(string $entityClass, string $alias, string $condition): self
    {
        return $this->join($entityClass, $alias, $condition, 'LEFT');
    }

    /**
     * Ajoute un ORDER BY
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$field} " . strtoupper($direction);
        return $this;
    }

    /**
     * Ajoute un GROUP BY
     */
    public function groupBy(string $field): self
    {
        $this->groupBy[] = $field;
        return $this;
    }

    /**
     * Définit la limite
     */
    public function setMaxResults(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Définit l'offset
     */
    public function setFirstResult(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Définit un paramètre
     */
    public function setParameter(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Construit et exécute la requête SQL
     *
     * @return array Résultats
     */
    public function getResult(): array
    {
        $sql = $this->buildSql();
        return $this->connection->fetchAll($sql, $this->parameters);
    }

    /**
     * Construit et exécute la requête SQL, retourne un seul résultat
     *
     * @return array|null Résultat ou null
     */
    public function getOneOrNullResult(): ?array
    {
        $sql = $this->buildSql();
        return $this->connection->fetchOne($sql, $this->parameters);
    }

    /**
     * Construit la requête SQL
     */
    private function buildSql(): string
    {
        $sql = 'SELECT ';
        
        // SELECT
        if (empty($this->select)) {
            $sql .= '*';
        } else {
            $sql .= implode(', ', $this->select);
        }
        
        // FROM
        if (empty($this->from)) {
            throw new \RuntimeException('La requête doit avoir au moins une clause FROM.');
        }
        
        $from = $this->from[0];
        $tableNameEscaped = $this->escapeIdentifier($from['table']);
        // Les alias ne doivent pas être échappés avec des backticks dans AS
        $sql .= " FROM {$tableNameEscaped} AS {$from['alias']}";
        
        // JOIN
        foreach ($this->join as $join) {
            $joinTableEscaped = $this->escapeIdentifier($join['table']);
            // Les alias ne doivent pas être échappés avec des backticks dans AS
            $sql .= " {$join['type']} JOIN {$joinTableEscaped} AS {$join['alias']} ON {$join['condition']}";
        }
        
        // WHERE
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' ', $this->where);
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        
        // LIMIT
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        // OFFSET
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        return $sql;
    }

    /**
     * Retourne la requête SQL construite (pour debug)
     */
    public function getSql(): string
    {
        return $this->buildSql();
    }
    
    /**
     * Valide qu'un identifiant SQL est valide
     * 
     * @param string $identifier Identifiant à valider
     * @throws \InvalidArgumentException Si l'identifiant n'est pas valide
     */
    private function validateIdentifier(string $identifier): void
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
     * Échappe un identifiant SQL avec des backticks
     * 
     * @param string $identifier Identifiant à échapper
     * @return string Identifiant échappé avec backticks
     */
    private function escapeIdentifier(string $identifier): string
    {
        $this->validateIdentifier($identifier);
        // Échapper les backticks en les doublant
        $escaped = str_replace('`', '``', $identifier);
        return "`{$escaped}`";
    }
    
    /**
     * Valide le type de JOIN
     * 
     * @param string $type Type de JOIN
     * @throws \InvalidArgumentException Si le type n'est pas valide
     */
    private function validateJoinType(string $type): void
    {
        $validTypes = ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'];
        if (!in_array(strtoupper($type), $validTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid join type: '{$type}'. Valid types are: " . implode(', ', $validTypes)
            );
        }
    }
}

