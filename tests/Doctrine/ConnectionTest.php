<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\Database\Connection;

/**
 * Tests pour Connection
 */
class ConnectionTest extends TestCase
{
    private Connection $connection;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];
        
        $this->connection = new Connection($config);
    }
    
    public function testInTransaction(): void
    {
        $this->assertFalse($this->connection->inTransaction());
        
        $this->connection->beginTransaction();
        $this->assertTrue($this->connection->inTransaction());
        
        $this->connection->commit();
        $this->assertFalse($this->connection->inTransaction());
    }
    
    public function testExecute(): void
    {
        $this->connection->execute(
            "CREATE TABLE test (id INTEGER PRIMARY KEY, name VARCHAR(255))"
        );
        
        $result = $this->connection->fetchAll("SELECT * FROM test");
        $this->assertIsArray($result);
    }
    
    public function testFetchOne(): void
    {
        $this->connection->execute(
            "CREATE TABLE test (id INTEGER PRIMARY KEY, name VARCHAR(255))"
        );
        
        $this->connection->execute(
            "INSERT INTO test (name) VALUES (:name)",
            ['name' => 'Test']
        );
        
        $result = $this->connection->fetchOne(
            "SELECT * FROM test WHERE id = :id",
            ['id' => 1]
        );
        
        $this->assertNotNull($result);
        $this->assertEquals('Test', $result['name']);
    }
}

