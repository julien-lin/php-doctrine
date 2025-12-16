<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\LazyLoader;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * Collection lazy pour les relations OneToMany
 * 
 * Charge automatiquement les entités liées lors du premier accès
 */
class LazyCollection implements ArrayAccess, Iterator, Countable
{
    private ?array $items = null;
    /** @var callable */
    private $loader;
    private int $position = 0;
    
    /**
     * Constructeur
     * 
     * @param callable $loader Fonction qui charge les entités (doit retourner un array)
     */
    public function __construct(callable $loader)
    {
        $this->loader = $loader;
    }
    
    /**
     * Charge les entités si elles ne sont pas déjà chargées
     * 
     * @return void
     */
    private function load(): void
    {
        if ($this->items === null) {
            $this->items = ($this->loader)();
            if (!is_array($this->items)) {
                $this->items = [];
            }
        }
    }
    
    /**
     * ArrayAccess: Vérifie si un offset existe
     */
    public function offsetExists(mixed $offset): bool
    {
        $this->load();
        return isset($this->items[$offset]);
    }
    
    /**
     * ArrayAccess: Récupère un élément
     */
    public function offsetGet(mixed $offset): mixed
    {
        $this->load();
        return $this->items[$offset] ?? null;
    }
    
    /**
     * ArrayAccess: Définit un élément
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->load();
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }
    
    /**
     * ArrayAccess: Supprime un élément
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->load();
        unset($this->items[$offset]);
    }
    
    /**
     * Iterator: Retourne l'élément courant
     */
    public function current(): mixed
    {
        $this->load();
        return $this->items[$this->position] ?? null;
    }
    
    /**
     * Iterator: Retourne la clé courante
     */
    public function key(): int
    {
        return $this->position;
    }
    
    /**
     * Iterator: Passe à l'élément suivant
     */
    public function next(): void
    {
        $this->position++;
    }
    
    /**
     * Iterator: Remet le pointeur au début
     */
    public function rewind(): void
    {
        $this->position = 0;
    }
    
    /**
     * Iterator: Vérifie si la position est valide
     */
    public function valid(): bool
    {
        $this->load();
        return isset($this->items[$this->position]);
    }
    
    /**
     * Countable: Retourne le nombre d'éléments
     */
    public function count(): int
    {
        $this->load();
        return count($this->items);
    }
    
    /**
     * Convertit la collection en tableau
     * 
     * @return array Tableau d'entités
     */
    public function toArray(): array
    {
        $this->load();
        return $this->items;
    }
    
    /**
     * Vérifie si la collection est vide
     * 
     * @return bool True si vide
     */
    public function isEmpty(): bool
    {
        $this->load();
        return empty($this->items);
    }
}

