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
    private array $having = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $parameters = [];
    private array $unions = [];
    private bool $unionAll = false;
    private int $paramCounter = 0;

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
            $paramName = 'param_' . $this->paramCounter++;
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
            $paramName = 'param_' . $this->paramCounter++;
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
            $paramName = 'param_' . $this->paramCounter++;
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
     * Ajoute une clause HAVING
     *
     * @param string $condition Condition HAVING
     * @param mixed $value Valeur (optionnel)
     * @return self Instance pour chaînage
     */
    public function having(string $condition, mixed $value = null): self
    {
        if ($value !== null) {
            $paramName = 'having_param_' . $this->paramCounter++;
            $this->having[] = str_replace('?', ':' . $paramName, $condition);
            $this->parameters[$paramName] = $value;
        } else {
            $this->having[] = $condition;
        }
        return $this;
    }
    
    /**
     * Ajoute une clause AND HAVING
     */
    public function andHaving(string $condition, mixed $value = null): self
    {
        if ($value !== null) {
            $paramName = 'having_param_' . $this->paramCounter++;
            $this->having[] = 'AND ' . str_replace('?', ':' . $paramName, $condition);
            $this->parameters[$paramName] = $value;
        } else {
            $this->having[] = 'AND ' . $condition;
        }
        return $this;
    }
    
    /**
     * Ajoute une clause OR HAVING
     */
    public function orHaving(string $condition, mixed $value = null): self
    {
        if ($value !== null) {
            $paramName = 'having_param_' . $this->paramCounter++;
            $this->having[] = 'OR ' . str_replace('?', ':' . $paramName, $condition);
            $this->parameters[$paramName] = $value;
        } else {
            $this->having[] = 'OR ' . $condition;
        }
        return $this;
    }
    
    /**
     * Ajoute une fonction d'agrégation COUNT
     *
     * @param string $field Champ à compter
     * @param string|null $alias Alias pour le résultat
     * @return self Instance pour chaînage
     */
    public function count(string $field, ?string $alias = null): self
    {
        $expr = "COUNT({$field})";
        if ($alias !== null) {
            $expr .= " AS {$alias}";
        }
        $this->select[] = $expr;
        return $this;
    }
    
    /**
     * Ajoute une fonction d'agrégation SUM
     *
     * @param string $field Champ à sommer
     * @param string|null $alias Alias pour le résultat
     * @return self Instance pour chaînage
     */
    public function sum(string $field, ?string $alias = null): self
    {
        $expr = "SUM({$field})";
        if ($alias !== null) {
            $expr .= " AS {$alias}";
        }
        $this->select[] = $expr;
        return $this;
    }
    
    /**
     * Ajoute une fonction d'agrégation AVG
     *
     * @param string $field Champ pour la moyenne
     * @param string|null $alias Alias pour le résultat
     * @return self Instance pour chaînage
     */
    public function avg(string $field, ?string $alias = null): self
    {
        $expr = "AVG({$field})";
        if ($alias !== null) {
            $expr .= " AS {$alias}";
        }
        $this->select[] = $expr;
        return $this;
    }
    
    /**
     * Ajoute une fonction d'agrégation MIN
     *
     * @param string $field Champ pour le minimum
     * @param string|null $alias Alias pour le résultat
     * @return self Instance pour chaînage
     */
    public function min(string $field, ?string $alias = null): self
    {
        $expr = "MIN({$field})";
        if ($alias !== null) {
            $expr .= " AS {$alias}";
        }
        $this->select[] = $expr;
        return $this;
    }
    
    /**
     * Ajoute une fonction d'agrégation MAX
     *
     * @param string $field Champ pour le maximum
     * @param string|null $alias Alias pour le résultat
     * @return self Instance pour chaînage
     */
    public function max(string $field, ?string $alias = null): self
    {
        $expr = "MAX({$field})";
        if ($alias !== null) {
            $expr .= " AS {$alias}";
        }
        $this->select[] = $expr;
        return $this;
    }
    
    /**
     * Ajoute une condition WHERE avec sous-requête
     *
     * @param string $field Champ à comparer
     * @param string $operator Opérateur (IN, NOT IN, EXISTS, NOT EXISTS, etc.)
     * @param callable|QueryBuilder $subquery Sous-requête (callable ou QueryBuilder)
     * @return self Instance pour chaînage
     */
    public function whereSubquery(string $field, string $operator, callable|QueryBuilder $subquery): self
    {
        if (is_callable($subquery)) {
            $subQb = new QueryBuilder($this->connection, $this->metadataReader);
            $subquery($subQb);
            $subSql = $subQb->buildSql();
            $subParams = $subQb->getParameters();
        } else {
            $subSql = $subquery->buildSql();
            $subParams = $subquery->getParameters();
        }
        
        // Fusionner les paramètres de la sous-requête
        foreach ($subParams as $key => $value) {
            $newKey = 'subquery_' . $this->paramCounter++ . '_' . $key;
            $subSql = str_replace(':' . $key, ':' . $newKey, $subSql);
            $this->parameters[$newKey] = $value;
        }
        
        $this->where[] = "{$field} {$operator} ({$subSql})";
        return $this;
    }
    
    /**
     * Ajoute une condition WHERE EXISTS
     *
     * @param callable|QueryBuilder $subquery Sous-requête
     * @return self Instance pour chaînage
     */
    public function whereExists(callable|QueryBuilder $subquery): self
    {
        if (is_callable($subquery)) {
            $subQb = new QueryBuilder($this->connection, $this->metadataReader);
            $subquery($subQb);
            $subSql = $subQb->buildSql();
            $subParams = $subQb->getParameters();
        } else {
            $subSql = $subquery->buildSql();
            $subParams = $subquery->getParameters();
        }
        
        // Fusionner les paramètres de la sous-requête
        foreach ($subParams as $key => $value) {
            $newKey = 'exists_' . $this->paramCounter++ . '_' . $key;
            $subSql = str_replace(':' . $key, ':' . $newKey, $subSql);
            $this->parameters[$newKey] = $value;
        }
        
        $this->where[] = "EXISTS ({$subSql})";
        return $this;
    }
    
    /**
     * Ajoute une condition WHERE NOT EXISTS
     *
     * @param callable|QueryBuilder $subquery Sous-requête
     * @return self Instance pour chaînage
     */
    public function whereNotExists(callable|QueryBuilder $subquery): self
    {
        if (is_callable($subquery)) {
            $subQb = new QueryBuilder($this->connection, $this->metadataReader);
            $subquery($subQb);
            $subSql = $subQb->buildSql();
            $subParams = $subQb->getParameters();
        } else {
            $subSql = $subquery->buildSql();
            $subParams = $subquery->getParameters();
        }
        
        // Fusionner les paramètres de la sous-requête
        foreach ($subParams as $key => $value) {
            $newKey = 'not_exists_' . $this->paramCounter++ . '_' . $key;
            $subSql = str_replace(':' . $key, ':' . $newKey, $subSql);
            $this->parameters[$newKey] = $value;
        }
        
        $this->where[] = "NOT EXISTS ({$subSql})";
        return $this;
    }
    
    /**
     * Ajoute une UNION avec une autre requête
     *
     * @param QueryBuilder $queryBuilder Autre QueryBuilder à unir
     * @param bool $all Si true, utilise UNION ALL
     * @return self Instance pour chaînage
     */
    public function union(QueryBuilder $queryBuilder, bool $all = false): self
    {
        $this->unions[] = [
            'query' => $queryBuilder,
            'all' => $all,
        ];
        return $this;
    }
    
    /**
     * Récupère les paramètres de la requête
     *
     * @return array Paramètres
     */
    public function getParameters(): array
    {
        return $this->parameters;
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
            $whereClauses = [];
            foreach ($this->where as $index => $condition) {
                // Si ce n'est pas la première condition et qu'elle ne commence pas par AND/OR, ajouter AND
                if ($index > 0 && !preg_match('/^\s*(AND|OR)\s+/i', $condition)) {
                    $whereClauses[] = 'AND ' . $condition;
                } else {
                    $whereClauses[] = $condition;
                }
            }
            $sql .= ' WHERE ' . implode(' ', $whereClauses);
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' ', $this->having);
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
        
        // UNION
        if (!empty($this->unions)) {
            foreach ($this->unions as $union) {
                $unionSql = $union['query']->buildSql();
                $unionParams = $union['query']->getParameters();
                
                // Fusionner les paramètres de l'UNION
                foreach ($unionParams as $key => $value) {
                    $newKey = 'union_' . $this->paramCounter++ . '_' . $key;
                    $unionSql = str_replace(':' . $key, ':' . $newKey, $unionSql);
                    $this->parameters[$newKey] = $value;
                }
                
                $unionType = $union['all'] ? 'UNION ALL' : 'UNION';
                // SQLite et MySQL supportent UNION sans parenthèses autour de la sous-requête
                $sql .= " {$unionType} {$unionSql}";
            }
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

