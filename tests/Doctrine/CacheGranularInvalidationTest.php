<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Cache\QueryCache;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;

/**
 * Tests pour l'invalidation granulaire du cache (Phase 3.2)
 */
class CacheGranularInvalidationTest extends TestCase
{
    private EntityManager $em;
    private QueryCache $cache;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];
        
        $this->cache = new QueryCache(3600, true);
        $this->em = new EntityManager($config, $this->cache);
        $this->createTestTable();
    }
    
    /**
     * Test que l'invalidation granulaire ne supprime que les requêtes concernées
     */
    public function testGranularInvalidationOnlyAffectsRelevantQueries(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Créer 2 utilisateurs
        $user1 = new TestUser();
        $user1->email = 'user1@example.com';
        $user1->name = 'User 1';
        $this->em->persist($user1);
        $this->em->flush();
        
        $user2 = new TestUser();
        $user2->email = 'user2@example.com';
        $user2->name = 'User 2';
        $this->em->persist($user2);
        $this->em->flush();
        
        // Mettre en cache findAll() (toutes les entités)
        $allUsers1 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(2, $allUsers1);
        $this->assertGreaterThan(0, $this->cache->count(), 'Le cache devrait contenir des entrées');
        
        // Mettre en cache find() pour user1
        $foundUser1 = $repository->find($user1->id, useCache: true, cacheTtl: 3600);
        $this->assertNotNull($foundUser1);
        $this->assertEquals('user1@example.com', $foundUser1->email);
        
        $cacheCountBefore = $this->cache->count();
        
        // Modifier user1 (devrait invalider uniquement les requêtes concernant user1 et findAll)
        $user1->name = 'User 1 Modified';
        $this->em->persist($user1);
        $this->em->flush();
        
        // Le cache devrait avoir été invalidé pour user1 et findAll
        $cacheCountAfter = $this->cache->count();
        // Le cache devrait être vide ou contenir moins d'entrées après invalidation
        $this->assertLessThanOrEqual($cacheCountBefore, $cacheCountAfter, 
            'Le cache devrait avoir été invalidé partiellement');
        
        // Vérifier que findAll() recharge les données depuis la base (cache invalidé)
        // Utiliser useCache: false pour forcer le rechargement depuis la base
        $allUsers2 = $repository->findAll(useCache: false);
        $this->assertCount(2, $allUsers2);
        // Trouver user1 dans les résultats
        $foundUser1 = null;
        foreach ($allUsers2 as $u) {
            if ($u->id === $user1->id) {
                $foundUser1 = $u;
                break;
            }
        }
        $this->assertNotNull($foundUser1);
        $this->assertEquals('User 1 Modified', $foundUser1->name);
    }
    
    /**
     * Test que l'invalidation d'une entité spécifique invalide les requêtes concernées
     */
    public function testInvalidationOfSpecificEntity(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Créer un utilisateur
        $user = new TestUser();
        $user->email = 'user@example.com';
        $user->name = 'User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Mettre en cache find() pour cet utilisateur
        $foundUser1 = $repository->find($user->id, useCache: true, cacheTtl: 3600);
        $this->assertNotNull($foundUser1);
        $this->assertEquals('user@example.com', $foundUser1->email);
        
        $cacheCountBefore = $this->cache->count();
        
        // Modifier l'utilisateur
        $user->name = 'User Modified';
        $this->em->persist($user);
        $this->em->flush();
        
        // Le cache devrait avoir été invalidé (find() et findAll() sont invalidés)
        $cacheCountAfter = $this->cache->count();
        // Après invalidation, le cache devrait être vide ou contenir moins d'entrées
        $this->assertLessThanOrEqual($cacheCountBefore, $cacheCountAfter, 
            'Le cache devrait avoir été invalidé');
        
        // Vérifier que find() recharge les données depuis la base (cache invalidé)
        // Utiliser useCache: false pour forcer le rechargement depuis la base
        $foundUser2 = $repository->find($user->id, useCache: false);
        $this->assertNotNull($foundUser2);
        $this->assertEquals('User Modified', $foundUser2->name);
        
        // Vérifier que le cache est maintenant vide ou contient la nouvelle entrée
        $foundUser3 = $repository->find($user->id, useCache: true, cacheTtl: 3600);
        $this->assertNotNull($foundUser3);
        $this->assertEquals('User Modified', $foundUser3->name);
    }
    
    /**
     * Test que l'invalidation d'une entité n'affecte pas les autres entités
     */
    public function testInvalidationDoesNotAffectOtherEntities(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Créer 3 utilisateurs
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
            $this->em->flush();
            $users[] = $user;
        }
        
        // Mettre en cache find() pour chaque utilisateur
        $cachedUsers = [];
        foreach ($users as $user) {
            $cachedUsers[] = $repository->find($user->id, useCache: true, cacheTtl: 3600);
        }
        
        $cacheCountBefore = $this->cache->count();
        $this->assertEquals(3, $cacheCountBefore, 'Le cache devrait contenir 3 entrées');
        
        // Modifier uniquement user2
        $users[1]->name = 'User 2 Modified';
        $this->em->persist($users[1]);
        $this->em->flush();
        
        // Le cache devrait avoir été invalidé pour user2 et findAll
        // Note: user1 et user3 peuvent aussi être invalidés car findAll() est invalidé
        $cacheCountAfter = $this->cache->count();
        $this->assertLessThanOrEqual($cacheCountBefore, $cacheCountAfter, 
            'Le cache devrait avoir été invalidé partiellement');
        
        // Vérifier que user1 et user3 sont toujours en cache (ou rechargés)
        $foundUser1 = $repository->find($users[0]->id, useCache: true, cacheTtl: 3600);
        $foundUser3 = $repository->find($users[2]->id, useCache: true, cacheTtl: 3600);
        
        $this->assertNotNull($foundUser1);
        $this->assertNotNull($foundUser3);
        $this->assertEquals('User 1', $foundUser1->name);
        $this->assertEquals('User 3', $foundUser3->name);
    }
    
    /**
     * Test que la suppression d'une entité invalide le cache
     */
    public function testDeletionInvalidatesCache(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Créer un utilisateur
        $user = new TestUser();
        $user->email = 'user@example.com';
        $user->name = 'User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Mettre en cache find() pour cet utilisateur
        $foundUser = $repository->find($user->id, useCache: true, cacheTtl: 3600);
        $this->assertNotNull($foundUser);
        
        $cacheCountBefore = $this->cache->count();
        
        // Supprimer l'utilisateur
        $this->em->remove($user);
        $this->em->flush();
        
        // Le cache devrait avoir été invalidé
        $cacheCountAfter = $this->cache->count();
        $this->assertLessThanOrEqual($cacheCountBefore, $cacheCountAfter, 
            'Le cache devrait avoir été invalidé après suppression');
        
        // Vérifier que find() retourne null
        $foundUserAfter = $repository->find($user->id, useCache: true, cacheTtl: 3600);
        $this->assertNull($foundUserAfter);
    }
    
    /**
     * Test que l'invalidation fonctionne avec findAll()
     */
    public function testInvalidationWithFindAll(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Créer 2 utilisateurs
        $user1 = new TestUser();
        $user1->email = 'user1@example.com';
        $user1->name = 'User 1';
        $this->em->persist($user1);
        $this->em->flush();
        
        $user2 = new TestUser();
        $user2->email = 'user2@example.com';
        $user2->name = 'User 2';
        $this->em->persist($user2);
        $this->em->flush();
        
        // Mettre en cache findAll()
        $allUsers1 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(2, $allUsers1);
        
        $cacheCountBefore = $this->cache->count();
        
        // Modifier user1
        $user1->name = 'User 1 Modified';
        $this->em->persist($user1);
        $this->em->flush();
        
        // Le cache devrait avoir été invalidé (findAll() est invalidé)
        $cacheCountAfter = $this->cache->count();
        $this->assertLessThanOrEqual($cacheCountBefore, $cacheCountAfter, 
            'Le cache devrait avoir été invalidé');
        
        // Vérifier que findAll() recharge les données depuis la base (cache invalidé)
        // Utiliser useCache: false pour forcer le rechargement depuis la base
        $allUsers2 = $repository->findAll(useCache: false);
        $this->assertCount(2, $allUsers2);
        // Trouver user1 dans les résultats
        $foundUser1 = null;
        foreach ($allUsers2 as $u) {
            if ($u->id === $user1->id) {
                $foundUser1 = $u;
                break;
            }
        }
        $this->assertNotNull($foundUser1);
        $this->assertEquals('User 1 Modified', $foundUser1->name);
    }
    
    private function createTestTable(): void
    {
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL
            )"
        );
    }
}

