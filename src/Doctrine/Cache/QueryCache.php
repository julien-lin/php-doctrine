<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Cache;

/**
 * Cache pour les résultats de requêtes
 * 
 * Permet de mettre en cache les résultats de requêtes fréquentes
 * avec un TTL (Time To Live) configurable.
 */
class QueryCache
{
    /**
     * Cache en mémoire (tableau associatif)
     * Structure : ['cache_key' => ['data' => ..., 'expires_at' => timestamp]]
     */
    private array $cache = [];
    
    /**
     * TTL par défaut en secondes (1 heure)
     */
    private int $defaultTtl = 3600;
    
    /**
     * Active ou désactive le cache
     */
    private bool $enabled = true;
    
    /**
     * Constructeur
     * 
     * @param int $defaultTtl TTL par défaut en secondes
     * @param bool $enabled Active ou désactive le cache
     */
    public function __construct(int $defaultTtl = 3600, bool $enabled = true)
    {
        $this->defaultTtl = $defaultTtl;
        $this->enabled = $enabled;
    }
    
    /**
     * Récupère une valeur du cache
     * 
     * @param string $key Clé du cache
     * @return mixed|null Valeur en cache ou null si non trouvée/expirée
     */
    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return null;
        }
        
        // Nettoyer les entrées expirées
        $this->cleanExpired();
        
        if (!isset($this->cache[$key])) {
            return null;
        }
        
        $entry = $this->cache[$key];
        
        // Vérifier si l'entrée a expiré
        if ($entry['expires_at'] < time()) {
            unset($this->cache[$key]);
            return null;
        }
        
        return $entry['data'];
    }
    
    /**
     * Stocke une valeur dans le cache
     * 
     * @param string $key Clé du cache
     * @param mixed $value Valeur à stocker
     * @param int|null $ttl TTL en secondes (null = TTL par défaut)
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $ttl = $ttl ?? $this->defaultTtl;
        $expiresAt = time() + $ttl;
        
        $this->cache[$key] = [
            'data' => $value,
            'expires_at' => $expiresAt,
        ];
    }
    
    /**
     * Supprime une entrée du cache
     * 
     * @param string $key Clé du cache
     * @return void
     */
    public function delete(string $key): void
    {
        unset($this->cache[$key]);
    }
    
    /**
     * Vide tout le cache
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [];
    }
    
    /**
     * Génère une clé de cache à partir d'une requête SQL et de ses paramètres
     * 
     * @param string $sql Requête SQL
     * @param array $params Paramètres de la requête
     * @return string Clé de cache
     */
    public function generateKey(string $sql, array $params = []): string
    {
        // Normaliser la requête SQL (supprimer les espaces multiples, etc.)
        $normalizedSql = preg_replace('/\s+/', ' ', trim($sql));
        
        // Trier les paramètres pour avoir une clé cohérente
        ksort($params);
        
        // Créer une clé unique
        $keyData = $normalizedSql . '|' . serialize($params);
        
        return 'query_' . md5($keyData);
    }
    
    /**
     * Nettoie les entrées expirées du cache
     * 
     * @return void
     */
    private function cleanExpired(): void
    {
        $now = time();
        
        foreach ($this->cache as $key => $entry) {
            if ($entry['expires_at'] < $now) {
                unset($this->cache[$key]);
            }
        }
    }
    
    /**
     * Active ou désactive le cache
     * 
     * @param bool $enabled True pour activer, false pour désactiver
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    
    /**
     * Vérifie si le cache est activé
     * 
     * @return bool True si le cache est activé
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Définit le TTL par défaut
     * 
     * @param int $ttl TTL en secondes
     * @return void
     */
    public function setDefaultTtl(int $ttl): void
    {
        $this->defaultTtl = $ttl;
    }
    
    /**
     * Récupère le TTL par défaut
     * 
     * @return int TTL en secondes
     */
    public function getDefaultTtl(): int
    {
        return $this->defaultTtl;
    }
    
    /**
     * Retourne le nombre d'entrées dans le cache
     * 
     * @return int Nombre d'entrées
     */
    public function count(): int
    {
        $this->cleanExpired();
        return count($this->cache);
    }
    
    /**
     * Invalide le cache pour une entité spécifique
     * Utile quand une entité est modifiée/supprimée
     * 
     * @param string $entityClass Classe de l'entité
     * @param int|string|null $entityId ID de l'entité (null = toutes les entités de cette classe)
     * @return void
     */
    public function invalidateEntity(string $entityClass, int|string|null $entityId = null): void
    {
        // Pour simplifier, on invalide tout le cache quand une entité est modifiée
        // Une implémentation plus fine pourrait invalider seulement les requêtes concernées
        $this->clear();
    }
}
