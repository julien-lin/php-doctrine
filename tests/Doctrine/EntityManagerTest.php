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
    
    public function testTransactionSuccess(): void
    {
        $this->createTestTable();
        
        // Exécuter une transaction qui réussit
        $result = $this->em->transaction(function($em) {
            $user = new TestUser();
            $user->email = 'transaction@example.com';
            $user->name = 'Transaction User';
            $em->persist($user);
            $em->flush();
            return $user;
        });
        
        // Vérifier que l'entité a été persistée (commit effectué)
        $this->assertNotNull($result);
        $this->assertNotNull($result->id);
        
        // Vérifier que l'entité existe en base
        $found = $this->em->find(TestUser::class, $result->id);
        $this->assertNotNull($found);
        $this->assertEquals('transaction@example.com', $found->email);
    }
    
    public function testTransactionRollbackOnException(): void
    {
        $this->createTestTable();
        
        // Exécuter une transaction qui échoue
        try {
            $this->em->transaction(function($em) {
                $user = new TestUser();
                $user->email = 'rollback@example.com';
                $user->name = 'Rollback User';
                $em->persist($user);
                $em->flush();
                
                // Lever une exception pour déclencher le rollback
                throw new \RuntimeException('Test exception');
            });
            
            $this->fail('Une exception aurait dû être levée');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Test exception', $e->getMessage());
        }
        
        // Vérifier que l'entité n'a pas été persistée (rollback effectué)
        $users = $this->em->getRepository(TestUser::class)->findAll();
        $this->assertCount(0, $users);
    }
    
    public function testTransactionReturnValue(): void
    {
        $this->createTestTable();
        
        // Tester que la valeur retournée par le callback est bien retournée
        $result = $this->em->transaction(function($em) {
            return 'test value';
        });
        
        $this->assertEquals('test value', $result);
    }
    
    public function testTransactionWithMultipleOperations(): void
    {
        $this->createTestTable();
        
        // Tester une transaction avec plusieurs opérations
        $results = $this->em->transaction(function($em) {
            $user1 = new TestUser();
            $user1->email = 'user1@example.com';
            $user1->name = 'User 1';
            $em->persist($user1);
            
            $user2 = new TestUser();
            $user2->email = 'user2@example.com';
            $user2->name = 'User 2';
            $em->persist($user2);
            
            $em->flush();
            
            return [$user1, $user2];
        });
        
        // Vérifier que les deux entités ont été persistées
        $this->assertCount(2, $results);
        $this->assertNotNull($results[0]->id);
        $this->assertNotNull($results[1]->id);
        
        // Vérifier qu'elles existent en base
        $found1 = $this->em->find(TestUser::class, $results[0]->id);
        $found2 = $this->em->find(TestUser::class, $results[1]->id);
        $this->assertNotNull($found1);
        $this->assertNotNull($found2);
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

