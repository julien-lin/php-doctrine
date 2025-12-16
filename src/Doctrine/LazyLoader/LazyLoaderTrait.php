<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\LazyLoader;

use JulienLinard\Doctrine\Repository\EntityRepository;

/**
 * Trait pour le lazy loading automatique des relations OneToMany
 * 
 * Ce trait doit être utilisé dans les entités qui ont des relations OneToMany
 * pour activer le chargement automatique lors de l'accès aux propriétés.
 * 
 * Note: Les propriétés OneToMany doivent être initialisées avec LazyCollection
 * lors de l'hydratation pour que le lazy loading fonctionne.
 */
trait LazyLoaderTrait
{
    /**
     * Repository associé à cette entité (pour le lazy loading)
     * @var EntityRepository|null
     */
    private ?EntityRepository $_repository = null;
    
    /**
     * Définit le repository pour cette entité (appelé automatiquement par EntityRepository)
     * 
     * @param EntityRepository $repository Repository
     * @return void
     */
    public function _setRepository(EntityRepository $repository): void
    {
        $this->_repository = $repository;
    }
}

