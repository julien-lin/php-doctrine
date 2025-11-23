# Doctrine PHP - Doctrine Style ORM

[🇫🇷 Read in French](README.fr.md) | [🇬🇧 Read in English](README.md)

## 💝 Support the project

If this bundle is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support the development and maintenance of this open source project.

---

A modern ORM (Object-Relational Mapping) for PHP 8+ inspired by Doctrine, with Entity Manager, Repository Pattern, Query Builder and PHP 8 Attributes mapping.

## 🚀 Installation

```bash
composer require julienlinard/doctrine-php
```

**Requirements**: PHP 8.0 or higher, PDO extension

## ⚡ Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

// Define an entity
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

// Database configuration
$config = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'dbname' => 'mydatabase',
    'user' => 'root',
    'password' => 'password'
];

// Create the Entity Manager
$em = new EntityManager($config);

// Create a user
$user = new User();
$user->email = 'test@example.com';
$user->password = password_hash('password', PASSWORD_BCRYPT);
$em->persist($user);
$em->flush();

// Retrieve a user
$user = $em->getRepository(User::class)->find(1);
```

## 📋 Features

- ✅ **Entity Manager** - Entity lifecycle management
- ✅ **Repository Pattern** - Repositories with CRUD methods
- ✅ **Query Builder** - Fluent SQL query construction
- ✅ **Attributes Mapping** - Entity definition with PHP 8 Attributes
- ✅ **Relations** - OneToMany, ManyToOne, ManyToMany
- ✅ **Migrations** - Schema migration system
- ✅ **Transactions** - Transaction management
- ✅ **Multi-DBMS** - MySQL, PostgreSQL, SQLite support

## 📖 Documentation

### Entity Definition

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

// Persist an entity
$user = new User();
$user->email = 'test@example.com';
$em->persist($user);
$em->flush();

// Retrieve an entity
$user = $em->find(User::class, 1);

// Update
$user->name = 'John Doe';
$em->flush();

// Delete
$em->remove($user);
$em->flush();
```

### Repository

#### Standard Repository

```php
$repository = $em->getRepository(User::class);

// Find by ID
$user = $repository->find(1);

// Find all
$users = $repository->findAll();

// Find by criteria
$users = $repository->findBy(['is_active' => true]);
$user = $repository->findOneBy(['email' => 'test@example.com']);
```

#### Custom Repository

To create a custom repository with shared MetadataReader (recommended for performance):

```php
use JulienLinard\Doctrine\Repository\EntityRepository;

class UserRepository extends EntityRepository
{
    public function __construct(EntityManager $em, string $entityClass)
    {
        // Use getMetadataReader() to share the instance
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

// Create the custom repository
$userRepo = $em->createRepository(UserRepository::class, User::class);
$activeUsers = $userRepo->findActiveUsers();
```

**⚠️ Important**: Always use `$em->getMetadataReader()` instead of `new MetadataReader()` to avoid creating multiple instances and improve performance.

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

// Usage
$user = $em->getRepository(User::class)->find(1);
$posts = $user->posts; // Array of Post
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
// Start a transaction
$em->beginTransaction();

try {
    $user = new User();
    $user->email = 'test@example.com';
    $em->persist($user);
    
    $post = new Post();
    $post->title = 'My post';
    $post->user = $user;
    $em->persist($post);
    
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

### Migrations

The migration system allows you to automatically generate SQL migrations from your Doctrine entities.

#### Migration Generation

```php
use JulienLinard\Doctrine\EntityManager;
use App\Entity\User;
use App\Entity\Todo;

$em = new EntityManager($config);

// Generate a migration for an entity
$sql = $em->generateMigration(User::class);
echo $sql;

// Generate migrations for multiple entities
$sql = $em->generateMigrations([User::class, Todo::class]);
```

#### Migration Execution

```php
use JulienLinard\Doctrine\EntityManager;

$em = new EntityManager($config);
$runner = $em->getMigrationRunner();
$manager = $em->getMigrationManager();

// Generate migration name
$migrationName = $manager->generateMigrationName();

// Execute migration
$sql = $em->generateMigration(User::class);
if (!empty($sql)) {
    $runner->run($sql);
    $manager->markAsExecuted($migrationName);
    echo "Migration {$migrationName} applied successfully.\n";
}
```

#### Check Applied Migrations

```php
$manager = $em->getMigrationManager();
$executed = $manager->getExecutedMigrations();

foreach ($executed as $migration) {
    echo "✅ {$migration}\n";
}
```

#### Integrated CLI Script (Recommended)

The package includes a ready-to-use CLI script that automatically detects your database configuration.

**Direct usage from the package**:

```bash
# From your project (after installation via composer)
php vendor/julienlinard/doctrine-php/bin/doctrine-migrate generate
php vendor/julienlinard/doctrine-php/bin/doctrine-migrate migrate
php vendor/julienlinard/doctrine-php/bin/doctrine-migrate status
```

**Or via Composer**:

```bash
composer exec doctrine-migrate generate
composer exec doctrine-migrate migrate
composer exec doctrine-migrate status
```

**Create a symbolic link (recommended)**:

```bash
# Create a symbolic link in your project
ln -s vendor/julienlinard/doctrine-php/bin/doctrine-migrate bin/doctrine-migrate

# Then use directly
php bin/doctrine-migrate generate
php bin/doctrine-migrate migrate
php bin/doctrine-migrate status
```

**Automatic Configuration**:

The script automatically searches for configuration in this order:

1. Environment variable `DOCTRINE_CONFIG` (path to PHP file)
2. `config/database.php` (from current directory)
3. `../config/database.php` (from current directory)
4. Environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

**Example `config/database.php` file**:

```php
<?php

return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'dbname' => 'mydatabase',
    'user' => 'root',
    'password' => 'password',
    'charset' => 'utf8mb4',
];
```

**Available Commands**:

- `generate [EntityClass]` - Generates a migration for an entity or all entities
- `migrate` - Executes pending migrations
- `status` - Shows migration status
- `help` - Shows help

#### Custom CLI Script (Optional)

If you prefer to create your own custom CLI script:

**Create `bin/migrate.php` in your application**:

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use JulienLinard\Doctrine\EntityManager;

// Load configuration
$config = require __DIR__ . '/../config/database.php';
$em = new EntityManager($config);

// Get action from CLI arguments
$action = $argv[1] ?? 'status';
$entityClass = $argv[2] ?? null;

try {
    match ($action) {
        'generate' => generateMigration($em, $entityClass),
        'migrate' => executeMigrations($em),
        'status' => showStatus($em),
        default => throw new \InvalidArgumentException(
            "Unknown action: {$action}. Use 'generate', 'migrate' or 'status'"
        )
    };
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    exit(1);
}

function generateMigration(EntityManager $em, ?string $entityClass): void
{
    echo "🔍 Generating migration...\n\n";
    
    if ($entityClass) {
        $sql = $em->generateMigration($entityClass);
        if (empty($sql)) {
            echo "✅ No migration needed.\n";
            return;
        }
        echo "📄 Migration SQL:\n" . $sql . "\n";
    } else {
        // Generate for all entities
        $entities = [/* your entity classes */];
        $sql = $em->generateMigrations($entities);
        if (!empty($sql)) {
            $manager = $em->getMigrationManager();
            $migrationName = $manager->generateMigrationName();
            $filename = __DIR__ . '/../migrations/' . $migrationName . '.sql';
            file_put_contents($filename, $sql);
            echo "💾 Migration saved: {$filename}\n";
        }
    }
}

function executeMigrations(EntityManager $em): void
{
    $migrationsPath = __DIR__ . '/../migrations';
    $files = glob($migrationsPath . '/*.sql');
    $manager = $em->getMigrationManager();
    $runner = $em->getMigrationRunner();
    $executed = $manager->getExecutedMigrations();
    
    foreach ($files as $file) {
        $migrationName = basename($file, '.sql');
        if (!in_array($migrationName, $executed)) {
            echo "▶️  Executing {$migrationName}...\n";
            $sql = file_get_contents($file);
            $runner->run($sql);
            $manager->markAsExecuted($migrationName);
            echo "✅ Migration applied.\n";
        }
    }
}

function showStatus(EntityManager $em): void
{
    $manager = $em->getMigrationManager();
    $executed = $manager->getExecutedMigrations();
    
    echo "📊 Applied migrations: " . count($executed) . "\n";
    foreach ($executed as $migration) {
        echo "  ✅ {$migration}\n";
    }
}
```

**Make the script executable**:
```bash
chmod +x bin/migrate.php
```

**Usage**:
```bash
php bin/migrate.php generate          # Generates a migration
php bin/migrate.php generate App\Entity\User  # For a specific entity
php bin/migrate.php migrate            # Executes migrations
php bin/migrate.php status             # Shows status
```

> **Note**: `symfony/console` is optional and suggested only if you want to create more structured CLI commands with argument validation, options, etc. For simple usage, a native PHP script is sufficient.

### EntityManager Methods

#### `persist(object $entity): void`

Marks an entity for persistence.

```php
$user = new User();
$user->email = 'test@example.com';
$em->persist($user);
```

#### `flush(): void`

Executes all pending operations (INSERT, UPDATE, DELETE).

```php
$em->persist($user);
$em->flush(); // Executes INSERT
```

#### `remove(object $entity): void`

Marks an entity for deletion.

```php
$em->remove($user);
$em->flush(); // Executes DELETE
```

#### `find(string $entityClass, int|string $id): ?object`

Finds an entity by its ID.

```php
$user = $em->find(User::class, 1);
```

#### `getRepository(string $entityClass): EntityRepository`

Returns the repository of an entity.

```php
$userRepo = $em->getRepository(User::class);
$users = $userRepo->findAll();
```

#### `createRepository(string $repositoryClass, string $entityClass): EntityRepository`

Creates a custom repository with shared MetadataReader (recommended for performance).

```php
$userRepo = $em->createRepository(UserRepository::class, User::class);
$activeUsers = $userRepo->findActiveUsers();
```

#### `getConnection(): Connection`

Returns the database connection.

```php
$connection = $em->getConnection();
$rows = $connection->fetchAll('SELECT * FROM users');
```

#### `getMetadataReader(): MetadataReader`

Returns the MetadataReader (shared among all repositories).

```php
$metadataReader = $em->getMetadataReader();
$metadata = $metadataReader->getMetadata(User::class);
```

#### `beginTransaction(): void`

Starts a transaction.

```php
$em->beginTransaction();
```

#### `commit(): void`

Commits a transaction.

```php
$em->commit();
```

#### `rollback(): void`

Rolls back a transaction.

```php
$em->rollback();
```

#### `generateMigration(string $entityClass): string`

Generates a SQL migration for an entity.

```php
$sql = $em->generateMigration(User::class);
```

#### `generateMigrations(array $entityClasses): string`

Generates SQL migrations for multiple entities.

```php
$sql = $em->generateMigrations([User::class, Post::class]);
```

#### `getMigrationManager(): MigrationManager`

Returns the migration manager.

```php
$manager = $em->getMigrationManager();
$migrationName = $manager->generateMigrationName();
$executed = $manager->getExecutedMigrations();
```

#### `getMigrationRunner(): MigrationRunner`

Returns the migration runner.

```php
$runner = $em->getMigrationRunner();
$runner->run($sql);
```

## 🔗 Integration with Other Packages

### Integration with core-php

```php
<?php

use JulienLinard\Core\Application;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;

// Initialize the application
$app = Application::create(__DIR__);
$app->loadEnv();

// Configure EntityManager
$em = new EntityManager([
    'host' => $_ENV['DB_HOST'],
    'dbname' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS']
]);

// Use in a controller
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

### Integration with auth-php

```php
<?php

use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Auth\Models\UserInterface;
use JulienLinard\Auth\Models\Authenticatable;

// Define the User entity for auth-php
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
    
    // ... other properties
}

// Use with AuthManager
$em = new EntityManager($dbConfig);
$auth = new AuthManager([
    'user_class' => User::class,
    'entity_manager' => $em
]);
```

### Standalone Usage

`doctrine-php` can be used independently of all other packages.

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

// Standalone usage
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

Finds an entity by its ID.

```php
$user = $repository->find(1);
```

#### `findAll(): array`

Finds all entities.

```php
$users = $repository->findAll();
```

#### `findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array`

Finds entities by criteria.

```php
$users = $repository->findBy(['is_active' => true], ['created_at' => 'DESC'], 10, 0);
```

#### `findOneBy(array $criteria): ?object`

Finds an entity by criteria.

```php
$user = $repository->findOneBy(['email' => 'test@example.com']);
```

## 📝 License

MIT License - See the LICENSE file for more details.

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or a pull request.

## 💝 Support the project

If this bundle is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support the development and maintenance of this open source project.

---

**Developed with ❤️ by Julien Linard**
