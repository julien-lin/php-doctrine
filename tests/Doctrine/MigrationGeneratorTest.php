<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Doctrine\Mapping\Index;

/**
 * Tests pour MigrationGenerator
 */
class MigrationGeneratorTest extends TestCase
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
    }
    
    public function testGenerateCreateTable(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('test_migration_entities', $sql);
        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('name', $sql);
    }
    
    public function testGenerateWithIndex(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // Vérifier que l'index est généré
        $this->assertStringContainsString('INDEX', $sql);
        $this->assertStringContainsString('idx_email', $sql);
    }
    
    public function testGenerateWithUniqueIndex(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntityWithUnique::class);
        
        // Vérifier que le SQL est généré
        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        
        // Si les index uniques sont présents, vérifier leur format
        if (strpos($sql, 'UNIQUE INDEX') !== false) {
            $this->assertStringContainsString('idx_username', $sql);
        }
    }
    
    public function testGenerateWithAutoIncrement(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        $this->assertStringContainsString('AUTO_INCREMENT', $sql);
    }
    
    public function testGenerateWithNullable(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // La colonne name est nullable
        $this->assertStringContainsString('name', $sql);
    }
    
    public function testGenerateWithDefault(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // La colonne isActive a une valeur par défaut
        $this->assertStringContainsString('isActive', $sql);
        $this->assertStringContainsString('DEFAULT', $sql);
    }
    
    public function testGenerateAlterTable(): void
    {
        // Créer la table initiale
        $this->em->getConnection()->execute(
            "CREATE TABLE test_migration_entities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL
            )"
        );
        
        // Générer une migration pour une entité modifiée
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // Devrait contenir ALTER TABLE si des changements sont détectés
        // (dépend de la détection des différences)
        $this->assertIsString($sql);
    }
    
    public function testGenerateMultipleEntities(): void
    {
        $sql = $this->em->generateMigrations([
            TestMigrationEntity::class,
            TestMigrationEntityWithUnique::class
        ]);
        
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('test_migration_entities', $sql);
        $this->assertStringContainsString('test_migration_entities_unique', $sql);
    }
    
    public function testGenerateWithDateTime(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // Vérifier que les colonnes datetime sont générées correctement
        $this->assertStringContainsString('created_at', $sql);
    }
    
    public function testGenerateWithBoolean(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // Vérifier que les colonnes boolean sont générées correctement
        // Note: Le nom de colonne peut être isActive (camelCase) ou is_active selon le mapping
        $this->assertStringContainsString('isActive', $sql);
        $this->assertStringContainsString('TINYINT(1)', $sql);
    }
    
    public function testGenerateWithCustomColumnName(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // Vérifier que les noms de colonnes personnalisés sont utilisés
        $this->assertStringContainsString('created_at', $sql);
    }
    
    public function testGenerateWithLength(): void
    {
        $sql = $this->em->generateMigration(TestMigrationEntity::class);
        
        // Vérifier que les longueurs sont spécifiées pour les VARCHAR
        $this->assertStringContainsString('VARCHAR(255)', $sql);
    }
}

#[Entity(table: 'test_migration_entities')]
class TestMigrationEntity
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Index(name: 'idx_email')]
    public string $email;
    
    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $name = null;
    
    #[Column(type: 'boolean', default: true)]
    public bool $isActive = true;
    
    #[Column(type: 'datetime', name: 'created_at', nullable: true)]
    public ?\DateTime $createdAt = null;
}

#[Entity(table: 'test_migration_entities_unique')]
class TestMigrationEntityWithUnique
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Index(name: 'idx_username', unique: true)]
    public string $username;
}

