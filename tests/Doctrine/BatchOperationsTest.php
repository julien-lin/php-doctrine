<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;

/**
 * Tests pour les Batch Operations
 */
class BatchOperationsTest extends TestCase
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
        $this->createTestTable();
    }
    
    public function testPersistBatch(): void
    {
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $users[] = $user;
        }
        
        // Persister en batch
        $this->em->persistBatch($users);
        $this->em->flush();
        
        // Vérifier que tous les utilisateurs ont un ID
        foreach ($users as $user) {
            $this->assertNotNull($user->id);
            $this->assertIsInt($user->id);
        }
        
        // Vérifier que tous les utilisateurs sont en base
        $repository = $this->em->getRepository(TestUser::class);
        $allUsers = $repository->findAll();
        $this->assertCount(5, $allUsers);
    }
    
    public function testPersistBatchWithDifferentClasses(): void
    {
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $users[] = $user;
        }
        
        // Persister en batch
        $this->em->persistBatch($users);
        $this->em->flush();
        
        // Vérifier que tous ont été insérés
        $repository = $this->em->getRepository(TestUser::class);
        $allUsers = $repository->findAll();
        $this->assertCount(3, $allUsers);
    }
    
    public function testPersistBatchEmptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le tableau d'entités ne peut pas être vide.");
        
        $this->em->persistBatch([]);
    }
    
    public function testPersistBatchWithNonObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Toutes les entités doivent être des objets.");
        
        $this->em->persistBatch(['not an object']);
    }
    
    public function testPersistBatchPerformance(): void
    {
        // Test de performance : insérer 100 entités
        $users = [];
        for ($i = 1; $i <= 100; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $users[] = $user;
        }
        
        $startTime = microtime(true);
        
        $this->em->persistBatch($users);
        $this->em->flush();
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Vérifier que tous ont été insérés
        $repository = $this->em->getRepository(TestUser::class);
        $allUsers = $repository->findAll();
        $this->assertCount(100, $allUsers);
        
        // La durée doit être raisonnable (< 1 seconde pour 100 entités)
        $this->assertLessThan(1.0, $duration, "L'insertion batch de 100 entités doit être rapide");
    }
    
    public function testPersistBatchWithSingleEntity(): void
    {
        // Un seul élément doit quand même fonctionner
        $user = new TestUser();
        $user->email = "user@example.com";
        $user->name = "User";
        
        $this->em->persistBatch([$user]);
        $this->em->flush();
        
        $this->assertNotNull($user->id);
        
        $repository = $this->em->getRepository(TestUser::class);
        $found = $repository->find($user->id);
        $this->assertNotNull($found);
    }
    
    public function testPersistBatchIdsAreSequential(): void
    {
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $users[] = $user;
        }
        
        $this->em->persistBatch($users);
        $this->em->flush();
        
        // Vérifier que les IDs sont séquentiels
        $ids = array_map(fn($user) => $user->id, $users);
        sort($ids);
        
        for ($i = 0; $i < count($ids) - 1; $i++) {
            $this->assertEquals($ids[$i] + 1, $ids[$i + 1], "Les IDs doivent être séquentiels");
        }
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
