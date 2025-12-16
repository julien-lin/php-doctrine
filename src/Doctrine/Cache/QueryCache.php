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
     * Structure : ['cache_key' => ['data' => ..., 'expires_at' => timestamp, 'tags' => [...]]]
     */
    private array $cache = [];
    
    /**
     * Index des tags pour invalidation granulaire
     * Structure : ['entity_class:entity_id' => ['cache_key1', 'cache_key2', ...]]
     */
    private array $tagIndex = [];
    
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
            // Supprimer les tags associés
            if (isset($entry['tags'])) {
                $this->removeKeyFromTags($key, $entry['tags']);
            }
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
     * @param array $tags Tags pour invalidation granulaire (ex: ['User:1', 'User:*'])
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $ttl = $ttl ?? $this->defaultTtl;
        $expiresAt = time() + $ttl;
        
        // Supprimer les anciens tags de cette clé si elle existe déjà
        if (isset($this->cache[$key]['tags'])) {
            $this->removeKeyFromTags($key, $this->cache[$key]['tags']);
        }
        
        $this->cache[$key] = [
            'data' => $value,
            'expires_at' => $expiresAt,
            'tags' => $tags,
        ];
        
        // Ajouter la clé aux index des tags
        $this->addKeyToTags($key, $tags);
    }
    
    /**
     * Supprime une entrée du cache
     * 
     * @param string $key Clé du cache
     * @return void
     */
    public function delete(string $key): void
    {
        if (isset($this->cache[$key]['tags'])) {
            $this->removeKeyFromTags($key, $this->cache[$key]['tags']);
        }
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
        
        // Utiliser xxh3 si disponible (PHP 8.1+), sinon sha256 (plus sûr que MD5)
        if (function_exists('hash') && in_array('xxh3', hash_algos(), true)) {
            $hash = hash('xxh3', $keyData);
        } else {
            // Utiliser sha256 au lieu de MD5 pour des raisons de sécurité
            $hash = hash('sha256', $keyData);
        }
        
        // Utiliser les 16 premiers caractères pour garder une clé de taille raisonnable
        return 'query_' . substr($hash, 0, 16);
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
                // Supprimer les tags associés
                if (isset($entry['tags'])) {
                    $this->removeKeyFromTags($key, $entry['tags']);
                }
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
     * ✅ PHASE 3.2: Invalidation granulaire au lieu de globale
     * 
     * @param string $entityClass Classe de l'entité
     * @param int|string|null $entityId ID de l'entité (null = toutes les entités de cette classe)
     * @return void
     */
    public function invalidateEntity(string $entityClass, int|string|null $entityId = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        // Construire les tags à invalider
        $tagsToInvalidate = [];
        
        if ($entityId !== null) {
            // Invalider les requêtes concernant cette entité spécifique
            $tagsToInvalidate[] = $this->buildTag($entityClass, $entityId);
        }
        
        // Toujours invalider les requêtes concernant toutes les entités de cette classe
        // (ex: findAll(), findBy() sans critères spécifiques)
        $tagsToInvalidate[] = $this->buildTag($entityClass, '*');
        
        // Invalider toutes les clés associées à ces tags
        $keysToDelete = [];
        foreach ($tagsToInvalidate as $tag) {
            if (isset($this->tagIndex[$tag])) {
                foreach ($this->tagIndex[$tag] as $key) {
                    if (!in_array($key, $keysToDelete, true)) {
                        $keysToDelete[] = $key;
                    }
                }
            }
        }
        
        // Supprimer les clés du cache
        foreach ($keysToDelete as $key) {
            $this->delete($key);
        }
    }
    
    /**
     * Construit un tag à partir d'une classe d'entité et d'un ID
     * 
     * @param string $entityClass Classe de l'entité
     * @param int|string|null $entityId ID de l'entité ('*' pour toutes les entités)
     * @return string Tag
     */
    private function buildTag(string $entityClass, int|string|null $entityId): string
    {
        $id = $entityId === null ? '*' : (string)$entityId;
        return $entityClass . ':' . $id;
    }
    
    /**
     * Ajoute une clé aux index des tags
     * 
     * @param string $key Clé du cache
     * @param array $tags Tags
     * @return void
     */
    private function addKeyToTags(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            if (!isset($this->tagIndex[$tag])) {
                $this->tagIndex[$tag] = [];
            }
            if (!in_array($key, $this->tagIndex[$tag], true)) {
                $this->tagIndex[$tag][] = $key;
            }
        }
    }
    
    /**
     * Supprime une clé des index des tags
     * 
     * @param string $key Clé du cache
     * @param array $tags Tags
     * @return void
     */
    private function removeKeyFromTags(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            if (isset($this->tagIndex[$tag])) {
                $this->tagIndex[$tag] = array_values(array_filter(
                    $this->tagIndex[$tag],
                    fn($k) => $k !== $key
                ));
                
                // Supprimer le tag s'il n'a plus de clés
                if (empty($this->tagIndex[$tag])) {
                    unset($this->tagIndex[$tag]);
                }
            }
        }
    }
}
