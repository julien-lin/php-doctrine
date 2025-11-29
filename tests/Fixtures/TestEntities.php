<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests\Fixtures;

use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Doctrine\Mapping\ManyToOne;
use JulienLinard\Doctrine\Mapping\OneToMany;
use JulienLinard\Doctrine\Validation\Assert;

/**
 * Classes de test partagées pour tous les tests
 */

#[Entity(table: 'test_users')]
class TestUser
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
}

#[Entity(table: 'test_posts')]
class TestPost
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'integer')]
    public int $user_id;
    
    #[Column(type: 'integer')]
    public int $category_id;
    
    #[Column(type: 'string', length: 255)]
    public string $title;
    
    #[Column(type: 'integer')]
    public int $views = 0;
}

#[Entity(table: 'test_posts')]
class TestPostEntity
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'integer', name: 'user_id')]
    public int $userId;
    
    #[Column(type: 'string', length: 255)]
    public string $title;
    
    #[Column(type: 'string', nullable: true)]
    public ?string $content = null;
}

#[Entity(table: 'test_admins')]
class TestAdmin
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
}

#[Entity(table: 'test_users')]
class TestUserEntity
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
    
    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTime $createdAt = null;
    
    #[Column(type: 'boolean', default: true)]
    public bool $isActive = true;
}

// Classes pour les tests de validation
#[Entity(table: 'test_users')]
class TestUserWithValidation
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

// Classes pour les tests de relations
#[Entity(table: 'test_users')]
class TestUserWithRelations
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
    
    #[OneToMany(targetEntity: TestPostWithRelations::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    public array $posts = [];
}

#[Entity(table: 'test_posts')]
class TestPostWithRelations
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $title;
    
    #[Column(type: 'text', nullable: true)]
    public ?string $content = null;
    
    #[Column(type: 'integer', name: 'user_id')]
    public ?int $userId = null;
    
    #[ManyToOne(targetEntity: TestUserWithRelations::class, joinColumn: 'user_id')]
    public ?TestUserWithRelations $user = null;
}
