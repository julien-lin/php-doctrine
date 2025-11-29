<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Tests\Fixtures\TestUserWithRelations;
use JulienLinard\Doctrine\Tests\Fixtures\TestPostWithRelations;

/**
 * Tests pour les relations
 */
class RelationsTest extends TestCase
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
     * Test de relation ManyToOne : chargement automatique
     */
    public function testManyToOneRelation(): void
    {
        // Créer un utilisateur
        $user = new TestUserWithRelations();
        $user->email = 'user@example.com';
        $user->name = 'Test User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Créer un post avec relation ManyToOne (utiliser user_id directement)
        $this->em->getConnection()->execute(
            "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
            [$user->id, 'Test Post', 'Content']
        );
        
        // Charger le post
        $postId = (int)$this->em->getConnection()->getPdo()->lastInsertId();
        $loadedPost = $this->em->find(TestPostWithRelations::class, $postId);
        
        $this->assertNotNull($loadedPost);
        $this->assertNotNull($loadedPost->user);
        $this->assertEquals($user->id, $loadedPost->user->id);
        $this->assertEquals('user@example.com', $loadedPost->user->email);
    }
    
    /**
     * Test de relation OneToMany : chargement manuel
     */
    public function testOneToManyRelation(): void
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
        
        // Charger l'utilisateur
        $loadedUser = $this->em->find(TestUserWithRelations::class, $user->id);
        
        // Charger les relations OneToMany
        $this->em->loadRelations($loadedUser);
        
        $this->assertNotNull($loadedUser);
        $this->assertIsArray($loadedUser->posts);
        $this->assertCount(2, $loadedUser->posts);
    }
    
    /**
     * Test de cascade persist
     */
    public function testCascadePersist(): void
    {
        // Créer un utilisateur
        $user = new TestUserWithRelations();
        $user->email = 'user@example.com';
        $user->name = 'Test User';
        
        // Créer des posts avec cascade
        $post1 = new TestPostWithRelations();
        $post1->title = 'Post 1';
        $post1->content = 'Content 1';
        
        $post2 = new TestPostWithRelations();
        $post2->title = 'Post 2';
        $post2->content = 'Content 2';
        
        $user->posts = [$post1, $post2];
        
        // Persister seulement l'utilisateur (les posts seront persistés en cascade)
        $this->em->persist($user);
        $this->em->flush();
        
        // Vérifier que tout a été créé
        $this->assertNotNull($user->id);
        
        // Vérifier les relations
        $loadedUser = $this->em->find(TestUserWithRelations::class, $user->id);
        $this->em->loadRelations($loadedUser);
        $this->assertCount(2, $loadedUser->posts);
    }
    
    /**
     * Test de findAllWith (eager loading)
     */
    public function testFindAllWith(): void
    {
        // Créer des utilisateurs avec posts
        for ($i = 1; $i <= 3; $i++) {
            $user = new TestUserWithRelations();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
            $this->em->flush();
            
            // Créer 2 posts pour chaque utilisateur directement en SQL
            for ($j = 1; $j <= 2; $j++) {
                $this->em->getConnection()->execute(
                    "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
                    [$user->id, "Post {$j} for User {$i}", "Content"]
                );
            }
        }
        
        // Charger tous les utilisateurs avec leurs posts (eager loading)
        $repository = $this->em->getRepository(TestUserWithRelations::class);
        $users = $repository->findAllWith(['posts']);
        
        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $this->assertIsArray($user->posts);
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

