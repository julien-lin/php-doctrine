<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;

/**
 * Tests pour EntityManager
 */
class EntityManagerTest extends TestCase
{
    private EntityManager $em;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configuration SQLite en mémoire pour les tests
        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];
        
        $this->em = new EntityManager($config);
    }
    
    public function testPersistAndFlush(): void
    {
        // Créer la table de test
        $this->createTestTable();
        
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        $this->em->persist($user);
        $this->em->flush();
        
        $this->assertNotNull($user->id);
        $this->assertIsInt($user->id);
    }
    
    public function testFind(): void
    {
        $this->createTestTable();
        
        // Insérer un utilisateur directement
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (:email, :name)",
            ['email' => 'test@example.com', 'name' => 'Test User']
        );
        
        $user = $this->em->find(TestUser::class, 1);
        
        $this->assertNotNull($user);
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals('test@example.com', $user->email);
    }
    
    public function testDirtyChecking(): void
    {
        $this->createTestTable();
        
        // Insérer un utilisateur
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (:email, :name)",
            ['email' => 'test@example.com', 'name' => 'Test User']
        );
        
        $user = $this->em->find(TestUser::class, 1);
        $this->assertNotNull($user);
        
        // Vérifier que l'entité n'est pas dirty au départ
        $this->assertFalse($this->em->isDirty($user));
        
        // Modifier l'entité
        $user->name = 'Modified Name';
        
        // Vérifier que l'entité est maintenant dirty
        $this->assertTrue($this->em->isDirty($user));
        
        // Flush et vérifier que seule la colonne modifiée est mise à jour
        $this->em->persist($user);
        $this->em->flush();
        
        // Vérifier que l'entité n'est plus dirty après flush
        $this->assertFalse($this->em->isDirty($user));
    }
    
    public function testRemove(): void
    {
        $this->createTestTable();
        
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        $this->em->persist($user);
        $this->em->flush();
        
        $id = $user->id;
        $this->assertNotNull($id);
        
        $this->em->remove($user);
        $this->em->flush();
        
        // Vérifier que l'utilisateur a été supprimé
        $deleted = $this->em->find(TestUser::class, $id);
        $this->assertNull($deleted);
    }
    
    private function createTestTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS test_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL
        )";
        
        $this->em->getConnection()->execute($sql);
    }
}

