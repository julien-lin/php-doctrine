<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;

/**
 * Tests d'intégration pour EntityManager
 * Teste des scénarios réels d'utilisation
 */
class EntityManagerIntegrationTest extends TestCase
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
    
    /**
     * Test d'un scénario complet : création, modification, suppression
     */
    public function testCompleteLifecycle(): void
    {
        // 1. Création
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        $this->em->persist($user);
        $this->em->flush();
        
        $this->assertNotNull($user->id);
        $this->assertEquals(1, $user->id);
        
        // 2. Lecture
        $found = $this->em->find(TestUser::class, $user->id);
        $this->assertNotNull($found);
        $this->assertEquals('test@example.com', $found->email);
        $this->assertEquals('Test User', $found->name);
        
        // 3. Modification
        $found->name = 'Updated User';
        $this->em->persist($found); // Ré-enregistrer l'entité modifiée
        $this->em->flush();
        
        $updated = $this->em->find(TestUser::class, $user->id);
        $this->assertEquals('Updated User', $updated->name);
        
        // 4. Suppression
        $this->em->remove($updated);
        $this->em->flush();
        
        $deleted = $this->em->find(TestUser::class, $user->id);
        $this->assertNull($deleted);
    }
    
    /**
     * Test de transaction : rollback en cas d'erreur
     */
    public function testTransactionRollback(): void
    {
        $this->em->beginTransaction();
        
        try {
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
            
            // Simuler une erreur
            throw new \RuntimeException('Erreur de test');
            
        } catch (\RuntimeException $e) {
            $this->em->rollback();
        }
        
        // Vérifier que rien n'a été persisté
        $users = $this->em->getRepository(TestUser::class)->findAll();
        $this->assertCount(0, $users);
    }
    
    /**
     * Test de transaction : commit réussi
     */
    public function testTransactionCommit(): void
    {
        $this->em->beginTransaction();
        
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
        
        $this->em->commit();
        
        // Vérifier que tout a été persisté
        $users = $this->em->getRepository(TestUser::class)->findAll();
        $this->assertCount(2, $users);
    }
    
    /**
     * Test de dirty checking : seule la propriété modifiée est mise à jour
     */
    public function testDirtyCheckingIntegration(): void
    {
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Original Name';
        $this->em->persist($user);
        $this->em->flush();
        
        // Recharger l'entité pour que le dirty checking fonctionne
        $user = $this->em->find(TestUser::class, $user->id);
        
        // Modifier seulement le nom
        $user->name = 'Updated Name';
        $this->em->persist($user); // Ré-enregistrer
        $this->em->flush();
        
        // Vérifier que l'email n'a pas changé
        $updated = $this->em->find(TestUser::class, $user->id);
        $this->assertEquals('test@example.com', $updated->email);
        $this->assertEquals('Updated Name', $updated->name);
    }
    
    /**
     * Test de création multiple d'entités
     */
    public function testMultipleEntitiesCreation(): void
    {
        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
            $users[] = $user;
        }
        
        $this->em->flush();
        
        // Vérifier que tous ont été créés
        $allUsers = $this->em->getRepository(TestUser::class)->findAll();
        $this->assertCount(10, $allUsers);
        
        // Vérifier les IDs
        foreach ($users as $index => $user) {
            $this->assertEquals($index + 1, $user->id);
        }
    }
    
    /**
     * Test de recherche avec critères complexes
     */
    public function testComplexFindBy(): void
    {
        // Créer des utilisateurs de test
        for ($i = 1; $i <= 5; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
        }
        $this->em->flush();
        
        // Recherche avec critères
        $repository = $this->em->getRepository(TestUser::class);
        $users = $repository->findBy(['email' => 'user3@example.com']);
        
        $this->assertCount(1, $users);
        $this->assertEquals('User 3', $users[0]->name);
    }
    
    /**
     * Test avec méthode transaction() - succès
     */
    public function testTransactionMethodSuccess(): void
    {
        $result = $this->em->transaction(function($em) {
            $user1 = new TestUser();
            $user1->email = 'tx1@example.com';
            $user1->name = 'Transaction User 1';
            $em->persist($user1);
            
            $user2 = new TestUser();
            $user2->email = 'tx2@example.com';
            $user2->name = 'Transaction User 2';
            $em->persist($user2);
            
            $em->flush();
            
            return [$user1, $user2];
        });
        
        // Vérifier que les entités ont été persistées (commit effectué)
        $this->assertCount(2, $result);
        $this->assertNotNull($result[0]->id);
        $this->assertNotNull($result[1]->id);
        
        $allUsers = $this->em->getRepository(TestUser::class)->findAll();
        $this->assertCount(2, $allUsers);
    }
    
    /**
     * Test avec méthode transaction() - rollback automatique
     */
    public function testTransactionMethodRollback(): void
    {
        try {
            $this->em->transaction(function($em) {
                $user = new TestUser();
                $user->email = 'rollback@example.com';
                $user->name = 'Rollback User';
                $em->persist($user);
                $em->flush();
                
                throw new \RuntimeException('Test error');
            });
            
            $this->fail('Une exception aurait dû être levée');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Test error', $e->getMessage());
        }
        
        // Vérifier que rien n'a été persisté (rollback effectué)
        $users = $this->em->getRepository(TestUser::class)->findAll();
        $this->assertCount(0, $users);
    }
    
    /**
     * Test avec findOrFail
     */
    public function testFindOrFailIntegration(): void
    {
        $user = new TestUser();
        $user->email = 'findorfail@example.com';
        $user->name = 'FindOrFail User';
        $this->em->persist($user);
        $this->em->flush();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Trouver avec findOrFail
        $found = $repository->findOrFail($user->id);
        $this->assertEquals('findorfail@example.com', $found->email);
        
        // Vérifier que findOrFail lève une exception si non trouvé
        $this->expectException(\JulienLinard\Doctrine\Exceptions\EntityNotFoundException::class);
        $repository->findOrFail(999);
    }
    
    /**
     * Test avec findOneByOrFail
     */
    public function testFindOneByOrFailIntegration(): void
    {
        $user = new TestUser();
        $user->email = 'findonebyorfail@example.com';
        $user->name = 'FindOneByOrFail User';
        $this->em->persist($user);
        $this->em->flush();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Trouver avec findOneByOrFail
        $found = $repository->findOneByOrFail(['email' => 'findonebyorfail@example.com']);
        $this->assertEquals('FindOneByOrFail User', $found->name);
        
        // Vérifier que findOneByOrFail lève une exception si non trouvé
        $this->expectException(\JulienLinard\Doctrine\Exceptions\DoctrineException::class);
        $repository->findOneByOrFail(['email' => 'notfound@example.com']);
    }
    
    /**
     * Test avec batch operations
     */
    public function testBatchOperationsIntegration(): void
    {
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new TestUser();
            $user->email = "batch{$i}@example.com";
            $user->name = "Batch User {$i}";
            $users[] = $user;
        }
        
        // Utiliser persistBatch
        $this->em->persistBatch($users);
        $this->em->flush();
        
        // Vérifier que tous ont été créés
        $allUsers = $this->em->getRepository(TestUser::class)->findAll();
        $this->assertGreaterThanOrEqual(5, count($allUsers));
        
        // Vérifier les IDs
        foreach ($users as $user) {
            $this->assertNotNull($user->id);
        }
    }
    
    /**
     * Test avec query cache
     */
    public function testQueryCacheIntegration(): void
    {
        $user = new TestUser();
        $user->email = 'cache@example.com';
        $user->name = 'Cache User';
        $this->em->persist($user);
        $this->em->flush();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Premier appel sans cache
        $users1 = $repository->findAll(false);
        $this->assertCount(1, $users1);
        
        // Deuxième appel avec cache
        $users2 = $repository->findAll(true);
        $this->assertCount(1, $users2);
        $this->assertEquals($users1[0]->email, $users2[0]->email);
    }
    
    /**
     * Test avec query logging
     */
    public function testQueryLoggingIntegration(): void
    {
        $logger = $this->em->enableQueryLog(true);
        
        $user = new TestUser();
        $user->email = 'logging@example.com';
        $user->name = 'Logging User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Vérifier que les requêtes ont été loggées
        $logs = $logger->getLogs();
        $this->assertGreaterThan(0, count($logs));
        
        // Vérifier qu'il y a au moins une requête INSERT
        $hasInsert = false;
        foreach ($logs as $log) {
            if (stripos($log['sql'], 'INSERT') !== false) {
                $hasInsert = true;
                break;
            }
        }
        $this->assertTrue($hasInsert);
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

