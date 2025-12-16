<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\LazyLoader\LazyCollection;
use JulienLinard\Doctrine\Tests\Fixtures\TestUserWithRelations;
use JulienLinard\Doctrine\Tests\Fixtures\TestPostWithRelations;

/**
 * Tests pour le lazy loading automatique des relations OneToMany
 */
class LazyLoadingTest extends TestCase
{
    private EntityManager $em;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];
        
        $this->em = new EntityManager($config);
        $this->createTestTables();
    }
    
    /**
     * Test que les relations OneToMany sont chargées automatiquement lors de l'accès
     */
    public function testLazyLoadingAutomatic(): void
    {
        // Créer un utilisateur
        $user = new TestUserWithRelations();
        $user->email = 'user@example.com';
        $user->name = 'Test User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Créer des posts directement en SQL
        $this->em->getConnection()->execute(
            "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Post 1', 'Content 1']
        );
        $this->em->getConnection()->execute(
            "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Post 2', 'Content 2']
        );
        
        // Charger l'utilisateur (sans charger les relations explicitement)
        $loadedUser = $this->em->find(TestUserWithRelations::class, $user->id);
        
        $this->assertNotNull($loadedUser);
        
        // ✅ PHASE 3.1: L'accès à la propriété posts devrait charger automatiquement les relations
        // La propriété devrait être une LazyCollection qui se charge à la demande
        $this->assertInstanceOf(LazyCollection::class, $loadedUser->posts);
        
        // Accéder à la collection devrait charger les relations
        $posts = $loadedUser->posts;
        $this->assertIsArray($posts->toArray());
        $this->assertCount(2, $posts);
        
        // Vérifier que les posts sont bien chargés
        $this->assertEquals('Post 1', $posts[0]->title);
        $this->assertEquals('Post 2', $posts[1]->title);
    }
    
    /**
     * Test que le lazy loading fonctionne avec count()
     */
    public function testLazyLoadingWithCount(): void
    {
        // Créer un utilisateur avec posts
        $user = new TestUserWithRelations();
        $user->email = 'user@example.com';
        $user->name = 'Test User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Créer 3 posts
        for ($i = 1; $i <= 3; $i++) {
            $this->em->getConnection()->execute(
                "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
                [$user->id, "Post {$i}", "Content {$i}"]
            );
        }
        
        // Charger l'utilisateur
        $loadedUser = $this->em->find(TestUserWithRelations::class, $user->id);
        
        // ✅ PHASE 3.1: count() devrait déclencher le chargement automatique
        $this->assertInstanceOf(LazyCollection::class, $loadedUser->posts);
        $this->assertEquals(3, count($loadedUser->posts));
    }
    
    /**
     * Test que le lazy loading fonctionne avec foreach
     */
    public function testLazyLoadingWithForeach(): void
    {
        // Créer un utilisateur avec posts
        $user = new TestUserWithRelations();
        $user->email = 'user@example.com';
        $user->name = 'Test User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Créer 2 posts
        $this->em->getConnection()->execute(
            "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Post 1', 'Content 1']
        );
        $this->em->getConnection()->execute(
            "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Post 2', 'Content 2']
        );
        
        // Charger l'utilisateur
        $loadedUser = $this->em->find(TestUserWithRelations::class, $user->id);
        
        // ✅ PHASE 3.1: foreach devrait déclencher le chargement automatique
        $count = 0;
        foreach ($loadedUser->posts as $post) {
            $this->assertInstanceOf(TestPostWithRelations::class, $post);
            $count++;
        }
        
        $this->assertEquals(2, $count);
    }
    
    /**
     * Test que le lazy loading ne charge qu'une seule fois
     */
    public function testLazyLoadingCached(): void
    {
        // Créer un utilisateur avec posts
        $user = new TestUserWithRelations();
        $user->email = 'user@example.com';
        $user->name = 'Test User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Créer un post
        $this->em->getConnection()->execute(
            "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Post 1', 'Content 1']
        );
        
        // Charger l'utilisateur
        $loadedUser = $this->em->find(TestUserWithRelations::class, $user->id);
        
        // Accéder à la collection (première fois - charge)
        $posts1 = $loadedUser->posts;
        $this->assertInstanceOf(LazyCollection::class, $posts1);
        $this->assertCount(1, $posts1);
        
        // Accéder à la collection (deuxième fois - utilise le cache interne de LazyCollection)
        $posts2 = $loadedUser->posts;
        $this->assertInstanceOf(LazyCollection::class, $posts2);
        $this->assertCount(1, $posts2);
        
        // Les deux accès devraient retourner la même instance de LazyCollection
        $this->assertSame($posts1, $posts2);
    }
    
    /**
     * Test que le lazy loading fonctionne avec findAll
     */
    public function testLazyLoadingWithFindAll(): void
    {
        // Créer 2 utilisateurs avec posts
        for ($i = 1; $i <= 2; $i++) {
            $user = new TestUserWithRelations();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
            $this->em->flush();
            
            // Créer 2 posts pour chaque utilisateur
            for ($j = 1; $j <= 2; $j++) {
                $this->em->getConnection()->execute(
                    "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
                    [$user->id, "Post {$j} for User {$i}", "Content"]
                );
            }
        }
        
        // Charger tous les utilisateurs
        $repository = $this->em->getRepository(TestUserWithRelations::class);
        $users = $repository->findAll();
        
        $this->assertCount(2, $users);
        
        // ✅ PHASE 3.1: Chaque utilisateur devrait avoir une LazyCollection pour ses posts
        foreach ($users as $user) {
            $this->assertInstanceOf(LazyCollection::class, $user->posts);
            $this->assertCount(2, $user->posts);
        }
    }
    
    private function createTestTables(): void
    {
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL
            )"
        );
        
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT
            )"
        );
    }
}

