<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Mapping;

use Attribute;

/**
 * Attribute pour définir un index sur une colonne
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Index
{
    /**
     * Constructeur
     *
     * @param string|null $name Nom de l'index (optionnel, généré automatiquement si non fourni)
     * @param bool $unique Si true, crée un index unique
     */
    public function __construct(
        public ?string $name = null,
        public bool $unique = false
    ) {
    }
}

