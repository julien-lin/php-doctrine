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

#### OneToMany / ManyToOne

```php
use JulienLinard\Doctrine\Mapping\OneToMany;
use JulienLinard\Doctrine\Mapping\ManyToOne;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[OneToMany(targetEntity: Post::class, mappedBy: 'user')]
    public array $posts = [];
}

#[Entity(table: 'posts')]
class Post
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    public ?User $user = null;
    
    #[Column(type: 'string', length: 255)]
    public string $title;
}

// Utilisation
$user = $em->getRepository(User::class)->find(1);
$posts = $user->posts; // Array de Post
```

#### ManyToMany

```php
use JulienLinard\Doctrine\Mapping\ManyToMany;

#[Entity(table: 'users')]
class User
{
    #[ManyToMany(targetEntity: Role::class)]
    public array $roles = [];
}

#[Entity(table: 'roles')]
class Role
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 50)]
    public string $name;
}
```

### Transactions

```php
// Démarrer une transaction
$em->beginTransaction();

try {
    $user = new User();
    $user->email = 'test@example.com';
    $em->persist($user);
    
    $post = new Post();
    $post->title = 'Mon post';
    $post->user = $user;
    $em->persist($post);
    
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

### Méthodes EntityManager

#### `persist(object $entity): void`

Marque une entité pour persistance.

```php
$user = new User();
$user->email = 'test@example.com';
$em->persist($user);
```

#### `flush(): void`

Exécute toutes les opérations en attente (INSERT, UPDATE, DELETE).

```php
$em->persist($user);
$em->flush(); // Exécute l'INSERT
```

#### `remove(object $entity): void`

Marque une entité pour suppression.

```php
$em->remove($user);
$em->flush(); // Exécute le DELETE
```

#### `find(string $entityClass, int|string $id): ?object`

Trouve une entité par son ID.

```php
$user = $em->find(User::class, 1);
```

#### `getRepository(string $entityClass): EntityRepository`

Retourne le repository d'une entité.

```php
$userRepo = $em->getRepository(User::class);
$users = $userRepo->findAll();
```

#### `createRepository(string $repositoryClass, string $entityClass): EntityRepository`

Crée un repository personnalisé avec MetadataReader partagé (recommandé pour les performances).

```php
$userRepo = $em->createRepository(UserRepository::class, User::class);
$activeUsers = $userRepo->findActiveUsers();
```

#### `getConnection(): Connection`

Retourne la connexion à la base de données.

```php
$connection = $em->getConnection();
$rows = $connection->fetchAll('SELECT * FROM users');
```

#### `getMetadataReader(): MetadataReader`

Retourne le MetadataReader (partagé entre tous les repositories).

```php
$metadataReader = $em->getMetadataReader();
$metadata = $metadataReader->getMetadata(User::class);
```

#### `beginTransaction(): void`

Démarre une transaction.

```php
$em->beginTransaction();
```

#### `commit(): void`

Valide une transaction.

```php
$em->commit();
```

#### `rollback(): void`

Annule une transaction.

```php
$em->rollback();
```

## 🔗 Intégration avec les autres packages

### Intégration avec core-php

```php
<?php

use JulienLinard\Core\Application;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;

// Initialiser l'application
$app = Application::create(__DIR__);
$app->loadEnv();

// Configurer EntityManager
$em = new EntityManager([
    'host' => $_ENV['DB_HOST'],
    'dbname' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS']
]);

// Utiliser dans un contrôleur
class UserController extends Controller
{
    public function __construct(
        private EntityManager $em
    ) {}
    
    #[Route(path: '/users/{id}', methods: ['GET'], name: 'user.show')]
    public function show(int $id): Response
    {
        $user = $this->em->getRepository(User::class)->find($id);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }
        
        return $this->view('user/show', ['user' => $user]);
    }
}
```

### Intégration avec auth-php

```php
<?php

use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Auth\Models\UserInterface;
use JulienLinard\Auth\Models\Authenticatable;

// Définir l'entité User pour auth-php
#[Entity(table: 'users')]
class User implements UserInterface
{
    use Authenticatable;
    
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    public string $password;
    
    // ... autres propriétés
}

// Utiliser avec AuthManager
$em = new EntityManager($dbConfig);
$auth = new AuthManager([
    'user_class' => User::class,
    'entity_manager' => $em
]);
```

### Utilisation indépendante

`doctrine-php` peut être utilisé indépendamment de tous les autres packages.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

#[Entity(table: 'products')]
class Product
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
    
    #[Column(type: 'decimal', precision: 10, scale: 2)]
    public float $price;
}

// Utilisation standalone
$em = new EntityManager([
    'host' => 'localhost',
    'dbname' => 'mydb',
    'user' => 'root',
    'password' => 'password'
]);

$product = new Product();
$product->name = 'Laptop';
$product->price = 999.99;
$em->persist($product);
$em->flush();
```

## 📚 API Reference

### EntityRepository

#### `find(int|string $id): ?object`

Trouve une entité par son ID.

```php
$user = $repository->find(1);
```

#### `findAll(): array`

Trouve toutes les entités.

```php
$users = $repository->findAll();
```

#### `findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array`

Trouve des entités par critères.

```php
$users = $repository->findBy(['is_active' => true], ['created_at' => 'DESC'], 10, 0);
```

#### `findOneBy(array $criteria): ?object`

Trouve une entité par critères.

```php
$user = $repository->findOneBy(['email' => 'test@example.com']);
```

## 📝 License

MIT License - Voir le fichier LICENSE pour plus de détails.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

---

**Développé avec ❤️ par Julien Linard**

