<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Mapping;

use Attribute;

/**
 * Attribute pour définir une colonne
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * Constructeur
     *
     * @param string $type Type de la colonne (string, integer, boolean, datetime, etc.)
     * @param int|null $length Longueur maximale (pour string)
     * @param bool $nullable Si true, la colonne peut être NULL
     * @param mixed $default Valeur par défaut
     * @param string|null $name Nom de la colonne en base (si différent du nom de la propriété)
     * @param bool $autoIncrement Si true, la colonne est auto-incrémentée
     */
    public function __construct(
        public string $type = 'string',
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null,
        public ?string $name = null,
        public bool $autoIncrement = false
    ) {
    }
}

