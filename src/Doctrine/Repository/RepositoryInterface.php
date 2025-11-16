<?php

namespace JulienLinard\Doctrine\Repository;

/**
 * Interface pour les repositories
 */
interface RepositoryInterface
{
    /**
     * Trouve une entité par son ID
     *
     * @param int|string $id Identifiant
     * @return object|null Entité ou null si non trouvée
     */
    public function find(int|string $id): ?object;

    /**
     * Trouve toutes les entités
     *
     * @return array Tableau d'entités
     */
    public function findAll(): array;

    /**
     * Trouve des entités par critères
     *
     * @param array $criteria Critères de recherche
     * @param array|null $orderBy Ordre de tri
     * @param int|null $limit Limite de résultats
     * @param int|null $offset Offset
     * @return array Tableau d'entités
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * Trouve une entité par critères
     *
     * @param array $criteria Critères de recherche
     * @return object|null Entité ou null si non trouvée
     */
    public function findOneBy(array $criteria): ?object;
}

