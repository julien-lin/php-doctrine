<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Validation\ValidationException;
use JulienLinard\Doctrine\Validation\Assert;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

/**
 * Tests pour le système de validation
 */
class ValidationTest extends TestCase
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
     * Test de validation NotBlank
     */
    public function testNotBlankValidation(): void
    {
        $user = new TestUser();
        $user->email = ''; // Vide
        $user->name = 'Test';
        
        $this->expectException(ValidationException::class);
        $this->em->persist($user);
    }
    
    /**
     * Test de validation Email
     */
    public function testEmailValidation(): void
    {
        $user = new TestUser();
        $user->email = 'invalid-email'; // Email invalide
        $user->name = 'Test';
        
        $this->expectException(ValidationException::class);
        $this->em->persist($user);
    }
    
    /**
     * Test de validation Email valide
     */
    public function testValidEmail(): void
    {
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Test';
        
        // Ne doit pas lever d'exception
        $this->em->persist($user);
        $this->em->flush();
        
        $this->assertNotNull($user->id);
    }
    
    /**
     * Test de validation Length
     */
    public function testLengthValidation(): void
    {
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'AB'; // Trop court (min: 3)
        
        $this->expectException(ValidationException::class);
        $this->em->persist($user);
    }
    
    /**
     * Test de validation Range
     */
    public function testRangeValidation(): void
    {
        $product = new TestProduct();
        $product->name = 'Product';
        $product->price = -10; // Négatif (min: 0)
        
        $this->expectException(ValidationException::class);
        $this->em->persist($product);
    }
    
    /**
     * Test de validation désactivée
     */
    public function testValidationDisabled(): void
    {
        $this->em->setValidationEnabled(false);
        
        $user = new TestUser();
        $user->email = ''; // Vide, mais validation désactivée
        $user->name = 'Test';
        
        // Ne doit pas lever d'exception
        $this->em->persist($user);
        $this->em->flush();
        
        $this->assertNotNull($user->id);
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
        
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL
            )"
        );
    }
}

#[Entity(table: 'test_users')]
class TestUser
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Assert(type: 'NotBlank')]
    #[Assert(type: 'Email')]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    #[Assert(type: 'NotBlank')]
    #[Assert(type: 'Length', options: ['min' => 3, 'max' => 255])]
    public string $name;
}

#[Entity(table: 'test_products')]
class TestProduct
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Assert(type: 'NotBlank')]
    public string $name;
    
    #[Column(type: 'decimal')]
    #[Assert(type: 'Range', options: ['min' => 0, 'max' => 10000])]
    public float $price;
}

