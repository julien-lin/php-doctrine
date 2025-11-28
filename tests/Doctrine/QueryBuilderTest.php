<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

/**
 * Tests pour QueryBuilder
 */
class QueryBuilderTest extends TestCase
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
    
    public function testSelect(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->select(['u.id', 'u.email']);
        
        $sql = $qb->getSql();
        
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('u.id', $sql);
        $this->assertStringContainsString('u.email', $sql);
        $this->assertStringContainsString(',', $sql); // Vérifier qu'il y a plusieurs colonnes
    }
    
    public function testFrom(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u');
        
        $sql = $qb->getSql();
        
        $this->assertStringContainsString('FROM', $sql);
        $this->assertStringContainsString('test_users', $sql);
        $this->assertStringContainsString('u', $sql); // L'alias est présent (avec ou sans backticks)
    }
    
    public function testWhere(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->where('email = ?', 'test1@example.com');
        
        $results = $qb->getResult();
        
        $this->assertCount(1, $results);
        $this->assertIsArray($results[0]);
        $this->assertEquals('test1@example.com', $results[0]['email']);
    }
    
    public function testAndWhere(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->where('email = ?', 'test1@example.com')
           ->andWhere('name = ?', 'User 1');
        
        $results = $qb->getResult();
        
        $this->assertCount(1, $results);
        $this->assertEquals('User 1', $results[0]['name']);
    }
    
    public function testOrWhere(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->where('email = ?', 'test1@example.com')
           ->orWhere('email = ?', 'test2@example.com');
        
        $results = $qb->getResult();
        
        $this->assertCount(2, $results);
        $emails = array_column($results, 'email');
        $this->assertContains('test1@example.com', $emails);
        $this->assertContains('test2@example.com', $emails);
    }
    
    public function testJoin(): void
    {
        $this->insertTestDataWithPosts();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->join(TestPostEntity::class, 'p', 'p.user_id = u.id')
           ->select(['u.*', 'p.*']);
        
        $sql = $qb->getSql();
        
        $this->assertStringContainsString('JOIN', $sql);
        $this->assertStringContainsString('test_posts', $sql);
        $this->assertStringContainsString('p', $sql); // L'alias est présent (avec ou sans backticks)
    }
    
    public function testLeftJoin(): void
    {
        $this->insertTestDataWithPosts();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->leftJoin(TestPostEntity::class, 'p', 'p.user_id = u.id')
           ->select('u.*', 'p.*');
        
        $sql = $qb->getSql();
        
        $this->assertStringContainsString('LEFT JOIN', $sql);
    }
    
    public function testOrderBy(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->orderBy('email', 'ASC');
        
        $results = $qb->getResult();
        
        $this->assertCount(3, $results);
        $this->assertEquals('test1@example.com', $results[0]['email']);
    }
    
    public function testOrderByDesc(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->orderBy('email', 'DESC');
        
        $results = $qb->getResult();
        
        $this->assertEquals('test3@example.com', $results[0]['email']);
    }
    
    public function testGroupBy(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->groupBy('name');
        
        $sql = $qb->getSql();
        
        $this->assertStringContainsString('GROUP BY', $sql);
    }
    
    public function testSetMaxResults(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->setMaxResults(2);
        
        $results = $qb->getResult();
        
        $this->assertCount(2, $results);
    }
    
    public function testSetFirstResult(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->setFirstResult(1)
           ->setMaxResults(2);
        
        $results = $qb->getResult();
        
        $this->assertCount(2, $results);
        $this->assertEquals('test2@example.com', $results[0]['email']);
    }
    
    public function testSetParameter(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->where('email = :email')
           ->setParameter('email', 'test1@example.com');
        
        $results = $qb->getResult();
        
        $this->assertCount(1, $results);
    }
    
    public function testGetOneOrNullResult(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->where('email = ?', 'test1@example.com');
        
        $result = $qb->getOneOrNullResult();
        
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertEquals('test1@example.com', $result['email']);
    }
    
    public function testGetOneOrNullResultNotFound(): void
    {
        $this->insertTestData();
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->where('email = ?', 'notfound@example.com');
        
        $result = $qb->getOneOrNullResult();
        
        $this->assertNull($result);
    }
    
    public function testInvalidIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u; DROP TABLE users; --');
    }
    
    public function testInvalidJoinType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUserEntity::class, 'u')
           ->join(TestPostEntity::class, 'p', 'p.user_id = u.id', 'INVALID');
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
    
    private function insertTestDataWithPosts(): void
    {
        $this->insertTestData();
        
        $this->em->getConnection()->execute(
            "INSERT INTO test_posts (user_id, title, content) VALUES (:user_id, :title, :content)",
            ['user_id' => 1, 'title' => 'Post 1', 'content' => 'Content 1']
        );
    }
}

#[Entity(table: 'test_posts')]
class TestPostEntity
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'integer')]
    public int $userId;
    
    #[Column(type: 'string', length: 255)]
    public string $title;
    
    #[Column(type: 'string', nullable: true)]
    public ?string $content = null;
}

