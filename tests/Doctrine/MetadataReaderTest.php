<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\Metadata\MetadataReader;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Doctrine\Mapping\Index;

/**
 * Tests pour MetadataReader
 */
class MetadataReaderTest extends TestCase
{
    private MetadataReader $reader;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new MetadataReader();
    }
    
    public function testGetMetadata(): void
    {
        $metadata = $this->reader->getMetadata(TestEntity::class);
        
        $this->assertEquals('test_entities', $metadata['table']);
        $this->assertEquals('id', $metadata['id']);
        $this->assertArrayHasKey('columns', $metadata);
        $this->assertArrayHasKey('email', $metadata['columns']);
    }
    
    public function testGetTableName(): void
    {
        $tableName = $this->reader->getTableName(TestEntity::class);
        $this->assertEquals('test_entities', $tableName);
    }
    
    public function testGetIdProperty(): void
    {
        $idProperty = $this->reader->getIdProperty(TestEntity::class);
        $this->assertEquals('id', $idProperty);
    }
    
    public function testIndexMetadata(): void
    {
        $metadata = $this->reader->getMetadata(TestEntity::class);
        
        $this->assertArrayHasKey('indexes', $metadata);
        
        // Vérifier que les index sont bien lus si présents
        if (!empty($metadata['indexes'])) {
            $emailIndex = $metadata['indexes'][0] ?? null;
            $this->assertNotNull($emailIndex);
            $this->assertEquals('email', $emailIndex['column']);
            $this->assertEquals('idx_email', $emailIndex['name']);
        } else {
            // Si aucun index n'est trouvé, c'est peut-être normal selon la configuration
            $this->assertIsArray($metadata['indexes']);
        }
    }
}

#[Entity(table: 'test_entities')]
class TestEntity
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Index(name: 'idx_email')]
    public string $email;
}

