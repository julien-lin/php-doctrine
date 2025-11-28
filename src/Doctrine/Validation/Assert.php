<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Validation;

use Attribute;

/**
 * Attribut de base pour les validations
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Assert
{
    /**
     * Type de validation
     */
    public string $type;
    
    /**
     * Options de validation
     */
    public array $options;
    
    /**
     * Constructeur
     *
     * @param string $type Type de validation (NotBlank, Email, Length, etc.)
     * @param array $options Options spécifiques à chaque type de validation
     */
    public function __construct(string $type, array $options = [])
    {
        $this->type = $type;
        $this->options = $options;
    }
}

