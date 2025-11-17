# Doctrine PHP - ORM Style Doctrine

Un ORM (Object-Relational Mapping) moderne pour PHP 8+ inspiré de Doctrine, avec Entity Manager, Repository Pattern, Query Builder et mapping avec Attributes PHP 8.

## 🚀 Installation

```bash
composer require julienlinard/doctrine-php
```

**Requirements** : PHP 8.0 ou supérieur, extension PDO

## ⚡ Démarrage rapide

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

// Définir une entité
#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    public string $password;
}

// Configuration de la base de données
$config = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'dbname' => 'mydatabase',
    'user' => 'root',
    'password' => 'password'
];

// Créer l'Entity Manager
$em = new EntityManager($config);

// Créer un utilisateur
$user = new User();
$user->email = 'test@example.com';
$user->password = password_hash('password', PASSWORD_BCRYPT);
$em->persist($user);
$em->flush();

// Récupérer un utilisateur
$user = $em->getRepository(User::class)->find(1);
```

## 📋 Fonctionnalités

- ✅ **Entity Manager** - Gestion du cycle de vie des entités
- ✅ **Repository Pattern** - Repositories avec méthodes CRUD
- ✅ **Query Builder** - Construction fluide de requêtes SQL
- ✅ **Mapping avec Attributes** - Définition d'entités avec PHP 8 Attributes
- ✅ **Relations** - OneToMany, ManyToOne, ManyToMany
- ✅ **Migrations** - Système de migrations de schéma
- ✅ **Transactions** - Gestion des transactions
- ✅ **Multi-SGBD** - Support MySQL, PostgreSQL, SQLite

## 📖 Documentation

### Définition d'une Entité

```php
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $email;
    
    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $name = null;
    
    #[Column(type: 'boolean', default: true)]
    public bool $is_active = true;
    
    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTime $created_at = null;
}
```

### Entity Manager

```php
use JulienLinard\Doctrine\EntityManager;

$em = new EntityManager($config);

// Persister une entité
$user = new User();
$user->email = 'test@example.com';
$em->persist($user);
$em->flush();

// Récupérer une entité
$user = $em->find(User::class, 1);

// Mettre à jour
$user->name = 'John Doe';
$em->flush();

// Supprimer
$em->remove($user);
$em->flush();
```

### Repository

#### Repository standard

```php
$repository = $em->getRepository(User::class);

// Trouver par ID
$user = $repository->find(1);

// Trouver tous
$users = $repository->findAll();

// Trouver par critères
$users = $repository->findBy(['is_active' => true]);
$user = $repository->findOneBy(['email' => 'test@example.com']);
```

#### Repository personnalisé

Pour créer un repository personnalisé avec le MetadataReader partagé (recommandé pour les performances) :

```php
use JulienLinard\Doctrine\Repository\EntityRepository;

class UserRepository extends EntityRepository
{
    public function __construct(EntityManager $em, string $entityClass)
    {
        // Utiliser getMetadataReader() pour partager l'instance
        parent::__construct(
            $em->getConnection(), 
            $em->getMetadataReader(), 
            $entityClass
        );
    }
    
    public function findActiveUsers(): array
    {
        return $this->findBy(['is_active' => true]);
    }
}

// Créer le repository personnalisé
$userRepo = $em->createRepository(UserRepository::class, User::class);
$activeUsers = $userRepo->findActiveUsers();
```

**⚠️ Important** : Utilisez toujours `$em->getMetadataReader()` au lieu de `new MetadataReader()` pour éviter la création de multiples instances et améliorer les performances.

### Query Builder

```php
$qb = $em->createQueryBuilder();
$users = $qb->select('u')
    ->from(User::class, 'u')
    ->where('u.email = :email')
    ->andWhere('u.is_active = :active')
    ->setParameter('email', 'test@example.com')
    ->setParameter('active', true)
    ->orderBy('u.created_at', 'DESC')
    ->setMaxResults(10)
    ->getResult();
```

### Relations

```php
use JulienLinard\Doctrine\Mapping\OneToMany;
use JulienLinard\Doctrine\Mapping\ManyToOne;

#[Entity(table: 'users')]
class User
{
    #[OneToMany(targetEntity: Post::class, mappedBy: 'user')]
    public array $posts = [];
}

#[Entity(table: 'posts')]
class Post
{
    #[ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    public ?User $user = null;
}
```

## 📝 License

MIT License - Voir le fichier LICENSE pour plus de détails.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

---

**Développé avec ❤️ par Julien Linard**

