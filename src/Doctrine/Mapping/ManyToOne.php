<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Mapping;

use Attribute;

/**
 * Attribute pour définir une relation ManyToOne
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    /**
     * Constructeur
     *
     * @param string $targetEntity Classe de l'entité cible
     * @param string|null $inversedBy Nom de la propriété dans l'entité cible (pour relation bidirectionnelle)
     * @param string|null $joinColumn Nom de la colonne de jointure (clé étrangère)
     */
    public function __construct(
        public string $targetEntity,
        public ?string $inversedBy = null,
        public ?string $joinColumn = null
    ) {
    }
}

