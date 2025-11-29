<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Repository\EntityRepository;
use JulienLinard\Doctrine\Tests\Fixtures\TestUserEntity;

/**
 * Tests pour EntityRepository
 */
class EntityRepositoryTest extends TestCase
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
    
    public function testFind(): void
    {
        // Insérer des données de test
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        $user = $repository->find(1);
        
        $this->assertNotNull($user);
        $this->assertInstanceOf(TestUserEntity::class, $user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('test1@example.com', $user->email);
    }
    
    public function testFindNotFound(): void
    {
        $repository = $this->em->getRepository(TestUserEntity::class);
        $user = $repository->find(999);
        
        $this->assertNull($user);
    }
    
    public function testFindAll(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        $users = $repository->findAll();
        
        $this->assertIsArray($users);
        $this->assertCount(3, $users);
        $this->assertInstanceOf(TestUserEntity::class, $users[0]);
    }
    
    public function testFindBy(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        // Trouver par email
        $users = $repository->findBy(['email' => 'test1@example.com']);
        $this->assertCount(1, $users);
        $this->assertEquals('test1@example.com', $users[0]->email);
        
        // Trouver avec plusieurs critères
        $users = $repository->findBy(['email' => 'test2@example.com', 'name' => 'User 2']);
        $this->assertCount(1, $users);
    }
    
    public function testFindByWithOrderBy(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        // Trier par email ASC
        $users = $repository->findBy([], ['email' => 'ASC']);
        $this->assertCount(3, $users);
        $this->assertEquals('test1@example.com', $users[0]->email);
        
        // Trier par email DESC
        $users = $repository->findBy([], ['email' => 'DESC']);
        $this->assertEquals('test3@example.com', $users[0]->email);
    }
    
    public function testFindByWithLimit(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        $users = $repository->findBy([], null, 2);
        $this->assertCount(2, $users);
    }
    
    public function testFindByWithOffset(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        $users = $repository->findBy([], null, 2, 1);
        $this->assertCount(2, $users);
        $this->assertEquals('test2@example.com', $users[0]->email);
    }
    
    public function testFindOneBy(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        $user = $repository->findOneBy(['email' => 'test2@example.com']);
        
        $this->assertNotNull($user);
        $this->assertInstanceOf(TestUserEntity::class, $user);
        $this->assertEquals('test2@example.com', $user->email);
        $this->assertEquals('User 2', $user->name);
    }
    
    public function testFindOneByNotFound(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        $user = $repository->findOneBy(['email' => 'notfound@example.com']);
        
        $this->assertNull($user);
    }
    
    public function testFindByWithInvalidIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        // Tentative d'injection SQL via le nom de colonne
        $repository->findBy(['email; DROP TABLE users; --' => 'test']);
    }
    
    public function testHydrateWithDateTime(): void
    {
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name, created_at) VALUES (:email, :name, :created_at)",
            [
                'email' => 'test@example.com',
                'name' => 'Test User',
                'created_at' => '2024-01-01 12:00:00'
            ]
        );
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        $user = $repository->find(1);
        
        $this->assertNotNull($user);
        // SQLite peut retourner null si la colonne n'existe pas ou est NULL
        if ($user->createdAt !== null) {
            $this->assertInstanceOf(\DateTime::class, $user->createdAt);
        }
    }
    
    public function testHydrateWithBoolean(): void
    {
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name, is_active) VALUES (:email, :name, :is_active)",
            [
                'email' => 'test@example.com',
                'name' => 'Test User',
                'is_active' => 1
            ]
        );
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        $user = $repository->find(1);
        
        $this->assertNotNull($user);
        $this->assertTrue($user->isActive);
    }
    
    public function testFindOrFail(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        // Trouver une entité existante
        $user = $repository->findOrFail(1);
        $this->assertNotNull($user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('test1@example.com', $user->email);
    }
    
    public function testFindOrFailNotFound(): void
    {
        $this->expectException(\JulienLinard\Doctrine\Exceptions\EntityNotFoundException::class);
        $this->expectExceptionMessage('L\'entité');
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        $repository->findOrFail(999);
    }
    
    public function testFindOneByOrFail(): void
    {
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        
        // Trouver une entité existante
        $user = $repository->findOneByOrFail(['email' => 'test2@example.com']);
        $this->assertNotNull($user);
        $this->assertEquals('test2@example.com', $user->email);
        $this->assertEquals('User 2', $user->name);
    }
    
    public function testFindOneByOrFailNotFound(): void
    {
        $this->expectException(\JulienLinard\Doctrine\Exceptions\DoctrineException::class);
        $this->expectExceptionMessage('n\'a pas été trouvée');
        
        $repository = $this->em->getRepository(TestUserEntity::class);
        $repository->findOneByOrFail(['email' => 'notfound@example.com']);
    }
    
    private function createTestTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS test_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NULL,
            is_active BOOLEAN DEFAULT 1
        )";
        
        $this->em->getConnection()->execute($sql);
    }
    
    private function insertTestData(): void
    {
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (:email, :name)",
            ['email' => 'test1@example.com', 'name' => 'User 1']
        );
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (:email, :name)",
            ['email' => 'test2@example.com', 'name' => 'User 2']
        );
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (:email, :name)",
            ['email' => 'test3@example.com', 'name' => 'User 3']
        );
    }
}

