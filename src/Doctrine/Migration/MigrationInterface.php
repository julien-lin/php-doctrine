<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Migration;

/**
 * Interface pour les migrations avec support des rollbacks
 */
interface MigrationInterface
{
    /**
     * Exécute la migration (up)
     * 
     * @return string SQL à exécuter pour appliquer la migration
     */
    public function up(): string;
    
    /**
     * Annule la migration (down)
     * 
     * @return string SQL à exécuter pour annuler la migration
     */
    public function down(): string;
    
    /**
     * Retourne le nom de la migration
     * 
     * @return string Nom unique de la migration
     */
    public function getName(): string;
}

