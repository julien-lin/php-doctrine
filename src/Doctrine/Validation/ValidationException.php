<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Validation;

use RuntimeException;

/**
 * Exception levée lors d'une erreur de validation
 */
class ValidationException extends RuntimeException
{
    /**
     * Erreurs de validation par propriété
     * 
     * @var array<string, array<string>>
     */
    private array $errors = [];
    
    /**
     * Constructeur
     *
     * @param string $message Message d'erreur principal
     * @param array $errors Erreurs par propriété
     * @param int $code Code d'erreur
     * @param \Throwable|null $previous Exception précédente
     */
    public function __construct(
        string $message = '',
        array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }
    
    /**
     * Ajoute une erreur pour une propriété
     *
     * @param string $property Nom de la propriété
     * @param string $message Message d'erreur
     */
    public function addError(string $property, string $message): void
    {
        if (!isset($this->errors[$property])) {
            $this->errors[$property] = [];
        }
        $this->errors[$property][] = $message;
    }
    
    /**
     * Récupère toutes les erreurs
     *
     * @return array<string, array<string>> Erreurs par propriété
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Récupère les erreurs pour une propriété
     *
     * @param string $property Nom de la propriété
     * @return array<string> Messages d'erreur
     */
    public function getErrorsForProperty(string $property): array
    {
        return $this->errors[$property] ?? [];
    }
    
    /**
     * Vérifie s'il y a des erreurs
     *
     * @return bool True s'il y a des erreurs
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}

