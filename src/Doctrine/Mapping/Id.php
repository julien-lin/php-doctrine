<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Mapping;

use Attribute;

/**
 * Attribute pour définir un identifiant (clé primaire)
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Id
{
    public function __construct()
    {
    }
}

