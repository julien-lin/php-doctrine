<?php

namespace JulienLinard\Doctrine\Mapping;

use Attribute;

/**
 * Attribute pour définir une entité
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Entity
{
    /**
     * Constructeur
     *
     * @param string $table Nom de la table en base de données
     */
    public function __construct(
        public string $table
    ) {
    }
}

