<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Validation;

use JulienLinard\Doctrine\Metadata\MetadataReader;
use ReflectionClass;
use ReflectionProperty;

/**
 * Validateur d'entités Doctrine
 */
class Validator
{
    private MetadataReader $metadataReader;
    
    public function __construct(MetadataReader $metadataReader)
    {
        $this->metadataReader = $metadataReader;
    }
    
    /**
     * Valide une entité
     *
     * @param object $entity Entité à valider
     * @throws ValidationException Si la validation échoue
     */
    public function validate(object $entity): void
    {
        $className = get_class($entity);
        $metadata = $this->metadataReader->getMetadata($className);
        $reflection = new ReflectionClass($entity);
        
        $errors = [];
        
        // Valider toutes les propriétés avec des attributs Assert
        foreach ($metadata['columns'] ?? [] as $propertyName => $columnInfo) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            // Récupérer les attributs Assert
            $asserts = $property->getAttributes(Assert::class);
            
            foreach ($asserts as $assertAttribute) {
                $assert = $assertAttribute->newInstance();
                $validationResult = $this->validateValue($value, $assert, $propertyName);
                
                if ($validationResult !== null) {
                    if (!isset($errors[$propertyName])) {
                        $errors[$propertyName] = [];
                    }
                    $errors[$propertyName][] = $validationResult;
                }
            }
        }
        
        // Valider les relations si nécessaire
        $this->validateRelations($entity, $metadata, $reflection, $errors);
        
        // Si des erreurs ont été trouvées, lever une exception
        if (!empty($errors)) {
            $messages = [];
            foreach ($errors as $property => $propertyErrors) {
                $messages[] = "{$property}: " . implode(', ', $propertyErrors);
            }
            
            throw new ValidationException(
                "Erreurs de validation :\n" . implode("\n", $messages),
                $errors
            );
        }
    }
    
    /**
     * Valide une valeur selon un attribut Assert
     *
     * @param mixed $value Valeur à valider
     * @param Assert $assert Attribut de validation
     * @param string $propertyName Nom de la propriété (pour les messages)
     * @return string|null Message d'erreur ou null si valide
     */
    private function validateValue(mixed $value, Assert $assert, string $propertyName): ?string
    {
        return match ($assert->type) {
            'NotBlank' => $this->validateNotBlank($value, $propertyName, $assert->options),
            'Email' => $this->validateEmail($value, $propertyName, $assert->options),
            'Length' => $this->validateLength($value, $propertyName, $assert->options),
            'Range' => $this->validateRange($value, $propertyName, $assert->options),
            'Regex' => $this->validateRegex($value, $propertyName, $assert->options),
            'NotNull' => $this->validateNotNull($value, $propertyName, $assert->options),
            'Positive' => $this->validatePositive($value, $propertyName, $assert->options),
            'Negative' => $this->validateNegative($value, $propertyName, $assert->options),
            default => null,
        };
    }
    
    /**
     * Valide que la valeur n'est pas vide
     */
    private function validateNotBlank(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' ne peut pas être vide.";
            return $message;
        }
        return null;
    }
    
    /**
     * Valide que la valeur est un email valide
     */
    private function validateEmail(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null) {
            return null; // Null est géré par NotBlank si nécessaire
        }
        
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être un email valide.";
            return $message;
        }
        return null;
    }
    
    /**
     * Valide la longueur d'une chaîne
     */
    private function validateLength(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null) {
            return null; // Null est géré par NotBlank si nécessaire
        }
        
        if (!is_string($value)) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être une chaîne de caractères.";
            return $message;
        }
        
        $length = strlen($value);
        $min = $options['min'] ?? null;
        $max = $options['max'] ?? null;
        
        if ($min !== null && $length < $min) {
            $message = $options['minMessage'] ?? "Le champ '{$propertyName}' doit contenir au moins {$min} caractères.";
            return $message;
        }
        
        if ($max !== null && $length > $max) {
            $message = $options['maxMessage'] ?? "Le champ '{$propertyName}' doit contenir au plus {$max} caractères.";
            return $message;
        }
        
        return null;
    }
    
    /**
     * Valide qu'une valeur numérique est dans une plage
     */
    private function validateRange(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null) {
            return null; // Null est géré par NotBlank si nécessaire
        }
        
        if (!is_numeric($value)) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être un nombre.";
            return $message;
        }
        
        $numValue = (float)$value;
        $min = $options['min'] ?? null;
        $max = $options['max'] ?? null;
        
        if ($min !== null && $numValue < $min) {
            $message = $options['minMessage'] ?? "Le champ '{$propertyName}' doit être supérieur ou égal à {$min}.";
            return $message;
        }
        
        if ($max !== null && $numValue > $max) {
            $message = $options['maxMessage'] ?? "Le champ '{$propertyName}' doit être inférieur ou égal à {$max}.";
            return $message;
        }
        
        return null;
    }
    
    /**
     * Valide qu'une valeur correspond à une expression régulière
     */
    private function validateRegex(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null) {
            return null; // Null est géré par NotBlank si nécessaire
        }
        
        if (!is_string($value)) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être une chaîne de caractères.";
            return $message;
        }
        
        $pattern = $options['pattern'] ?? null;
        if ($pattern === null) {
            return null; // Pas de pattern, pas de validation
        }
        
        if (!preg_match($pattern, $value)) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' ne correspond pas au format requis.";
            return $message;
        }
        
        return null;
    }
    
    /**
     * Valide qu'une valeur n'est pas null
     */
    private function validateNotNull(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' ne peut pas être null.";
            return $message;
        }
        return null;
    }
    
    /**
     * Valide qu'une valeur numérique est positive
     */
    private function validatePositive(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null) {
            return null; // Null est géré par NotBlank si nécessaire
        }
        
        if (!is_numeric($value)) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être un nombre.";
            return $message;
        }
        
        if ((float)$value <= 0) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être positif.";
            return $message;
        }
        
        return null;
    }
    
    /**
     * Valide qu'une valeur numérique est négative
     */
    private function validateNegative(mixed $value, string $propertyName, array $options): ?string
    {
        if ($value === null) {
            return null; // Null est géré par NotBlank si nécessaire
        }
        
        if (!is_numeric($value)) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être un nombre.";
            return $message;
        }
        
        if ((float)$value >= 0) {
            $message = $options['message'] ?? "Le champ '{$propertyName}' doit être négatif.";
            return $message;
        }
        
        return null;
    }
    
    /**
     * Valide les relations d'une entité
     */
    private function validateRelations(
        object $entity,
        array $metadata,
        ReflectionClass $reflection,
        array &$errors
    ): void {
        foreach ($metadata['relations'] ?? [] as $propertyName => $relation) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            // Valider l'existence des entités liées ManyToOne
            if ($relation['type'] === 'ManyToOne' && $value !== null) {
                if (is_object($value)) {
                    // Vérifier que l'entité liée a un ID
                    $targetMetadata = $this->metadataReader->getMetadata($relation['targetEntity']);
                    $targetReflection = new ReflectionClass($value);
                    $idProperty = $targetReflection->getProperty($targetMetadata['id']);
                    $idProperty->setAccessible(true);
                    $id = $idProperty->getValue($value);
                    
                    if ($id === null || $id === 0) {
                        if (!isset($errors[$propertyName])) {
                            $errors[$propertyName] = [];
                        }
                        $errors[$propertyName][] = "L'entité liée '{$propertyName}' doit être persistée avant d'être associée.";
                    }
                }
            }
        }
    }
}

