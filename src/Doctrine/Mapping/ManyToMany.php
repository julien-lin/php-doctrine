<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Mapping;

use Attribute;

/**
 * Attribute pour définir une relation ManyToMany
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    /**
     * Constructeur
     *
     * @param string $targetEntity Classe de l'entité cible
     * @param string|null $mappedBy Nom de la propriété dans l'entité cible (pour relation bidirectionnelle)
     * @param string|null $joinTable Nom de la table de jointure
     */
    public function __construct(
        public string $targetEntity,
        public ?string $mappedBy = null,
        public ?string $joinTable = null
    ) {
    }
}

