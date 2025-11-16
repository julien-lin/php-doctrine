<?php

namespace JulienLinard\Doctrine\Mapping;

use Attribute;

/**
 * Attribute pour définir une relation OneToMany
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany
{
    /**
     * Constructeur
     *
     * @param string $targetEntity Classe de l'entité cible
     * @param string $mappedBy Nom de la propriété dans l'entité cible qui référence cette entité
     */
    public function __construct(
        public string $targetEntity,
        public string $mappedBy
    ) {
    }
}

