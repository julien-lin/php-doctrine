# Doctrine PHP - Modern ORM for PHP 8+

[🇫🇷 Read in French](README.fr.md) | [🇬🇧 Read in English](README.md)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-141%20passing-brightgreen.svg)](tests/)

A modern, lightweight ORM (Object-Relational Mapping) for PHP 8+ inspired by Doctrine ORM. Features Entity Manager, Repository Pattern, Query Builder, and PHP 8 Attributes mapping with automatic optimizations.

## ✨ Features

- 🚀 **Entity Manager** - Complete entity lifecycle management
- 📦 **Repository Pattern** - Powerful repositories with CRUD methods
- 🔨 **Query Builder** - Fluent SQL query construction
- 🏷️ **PHP 8 Attributes** - Modern entity definition with attributes
- 🔗 **Relations** - OneToMany, ManyToOne, ManyToMany support
- 📊 **Migrations** - Automatic schema migration system with rollback
- 🔄 **Transactions** - Full transaction support with automatic rollback
- ⚡ **Performance** - Query cache, batch operations, N+1 optimization
- 📝 **Query Logging** - Built-in SQL query logging for debugging
- 🗄️ **Multi-DBMS** - MySQL, PostgreSQL, SQLite support

## 🚀 Quick Start

### Installation

```bash
composer require julienlinard/doctrine-php
```

**Requirements**: PHP 8.0+ and PDO extension

### Basic Usage

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
    public string $name;
}

// Database configuration
$config = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'dbname' => 'mydatabase',
    'user' => 'root',
    'password' => 'password'
];

// Create Entity Manager
$em = new EntityManager($config);

// Create a user
$user = new User();
$user->email = 'john@example.com';
$user->name = 'John Doe';
$em->persist($user);
$em->flush();

// Retrieve a user
$user = $em->getRepository(User::class)->find(1);
echo $user->name; // John Doe
```

## 📖 Documentation

### Table of Contents

1. [Entity Definition](#entity-definition)
2. [Entity Manager](#entity-manager)
3. [Repository](#repository)
4. [Query Builder](#query-builder)
5. [Relations](#relations)
6. [Transactions](#transactions)
7. [Migrations](#migrations)
8. [Performance Features](#performance-features)
9. [Query Logging](#query-logging)
10. [API Reference](#api-reference)

---

### Entity Definition

Entities are defined using PHP 8 attributes:

```php
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Doctrine\Mapping\Index;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Index(unique: true)]
    public string $email;
    
    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $name = null;
    
    #[Column(type: 'boolean', default: true)]
    public bool $is_active = true;
    
    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTime $created_at = null;
}
```

#### Supported Column Types

- `string` / `varchar` - VARCHAR with optional length
- `text` - TEXT
- `integer` / `int` - INT
- `boolean` / `bool` - TINYINT(1) or BOOLEAN
- `float` / `double` - DOUBLE
- `decimal` - DECIMAL with precision/scale
- `datetime` - DATETIME
- `date` - DATE
- `time` - TIME
- `json` - JSON (auto serialization)

---

### Entity Manager

The Entity Manager is the central component for managing entities.

#### Basic Operations

```php
$em = new EntityManager($config);

// Create
$user = new User();
$user->email = 'test@example.com';
$user->name = 'Test User';
$em->persist($user);
$em->flush();

// Read
$user = $em->find(User::class, 1);

// Update
$user->name = 'Updated Name';
$em->persist($user); // Re-persist modified entity
$em->flush();

// Delete
$em->remove($user);
$em->flush();
```

#### Batch Operations

Insert multiple entities efficiently with a single query:

```php
$users = [];
for ($i = 1; $i <= 100; $i++) {
    $user = new User();
    $user->email = "user{$i}@example.com";
    $user->name = "User {$i}";
    $users[] = $user;
}

// Batch insert (optimized - single INSERT query)
$em->persistBatch($users);
$em->flush(); // Executes one INSERT with multiple VALUES
```

#### Transactions

Simplified transaction management with automatic rollback:

```php
// Method 1: Automatic transaction (recommended)
$result = $em->transaction(function($em) {
    $user = new User();
    $user->email = 'test@example.com';
    $em->persist($user);
    
    $post = new Post();
    $post->title = 'My Post';
    $post->user = $user;
    $em->persist($post);
    
    $em->flush();
    return $user; // Return value is preserved
});

// Method 2: Manual transaction
$em->beginTransaction();
try {
    $user = new User();
    $em->persist($user);
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

---

### Repository

Repositories provide convenient methods for querying entities.

#### Standard Methods

```php
$repository = $em->getRepository(User::class);

// Find by ID
$user = $repository->find(1);

// Find all
$users = $repository->findAll();

// Find by criteria
$users = $repository->findBy(['is_active' => true]);
$user = $repository->findOneBy(['email' => 'test@example.com']);

// Find or fail (throws exception if not found)
$user = $repository->findOrFail(1);
$user = $repository->findOneByOrFail(['email' => 'test@example.com']);
```

#### Advanced Queries

```php
// With ordering
$users = $repository->findBy(
    ['is_active' => true],
    ['created_at' => 'DESC']
);

// With pagination
$users = $repository->findBy(
    [],
    ['name' => 'ASC'],
    10,  // limit
    0    // offset
);

// With query cache
$users = $repository->findAll(true, 3600); // Cache for 1 hour
$users = $repository->findBy(
    ['is_active' => true],
    null, null, null,
    true,  // use cache
    3600   // TTL
);
```

#### Eager Loading (Optimized N+1)

Load relations efficiently with batch loading:

```php
// Load users with their posts (optimized - avoids N+1 queries)
$users = $repository->findAllWith(['posts']);

// Each user now has $user->posts loaded
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

#### Custom Repository

Create custom repositories with shared MetadataReader:

```php
use JulienLinard\Doctrine\Repository\EntityRepository;

class UserRepository extends EntityRepository
{
    public function findActiveUsers(): array
    {
        return $this->findBy(['is_active' => true]);
    }
    
    public function findByEmailDomain(string $domain): array
    {
        return $this->findBy([], ['email' => 'ASC'])
            ->filter(fn($user) => str_ends_with($user->email, $domain));
    }
}

// Create custom repository
$userRepo = $em->createRepository(UserRepository::class, User::class);
$activeUsers = $userRepo->findActiveUsers();
```

---

### Query Builder

Build complex SQL queries with a fluent interface:

```php
$qb = $em->createQueryBuilder();

// Basic query
$users = $qb->select('u')
    ->from(User::class, 'u')
    ->where('u.email = :email')
    ->andWhere('u.is_active = :active')
    ->setParameter('email', 'test@example.com')
    ->setParameter('active', true)
    ->orderBy('u.created_at', 'DESC')
    ->setMaxResults(10)
    ->getResult();

// Aggregations
$stats = $qb->select('u')
    ->from(User::class, 'u')
    ->count('u.id', 'total')
    ->sum('u.views', 'total_views')
    ->avg('u.rating', 'avg_rating')
    ->groupBy('u.category_id')
    ->having('total > :min')
    ->setParameter('min', 10)
    ->getResult();

// Subqueries
$users = $qb->select('u')
    ->from(User::class, 'u')
    ->whereSubquery('u.id', 'IN', function($subQb) {
        $subQb->from(Post::class, 'p')
              ->select('p.user_id')
              ->where('p.published = ?', true);
    })
    ->getResult();

// EXISTS
$users = $qb->select('u')
    ->from(User::class, 'u')
    ->whereExists(function($subQb) {
        $subQb->from(Post::class, 'p')
              ->where('p.user_id = u.id')
              ->where('p.published = ?', true);
    })
    ->getResult();

// UNION
$qb1 = $em->createQueryBuilder()
    ->from(User::class, 'u')
    ->select('u.id', 'u.name');
    
$qb2 = $em->createQueryBuilder()
    ->from(Admin::class, 'a')
    ->select('a.id', 'a.name');
    
$all = $qb->union($qb1, $qb2)->getResult();
```

---

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
    
    #[OneToMany(targetEntity: Post::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
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

// Load relations manually
$em->loadRelations($user, 'posts');

// Or use eager loading (optimized)
$users = $repository->findAllWith(['posts']);
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

**Note**: Automatic indexes are created on foreign key columns for optimal query performance.

---

### Transactions

#### Automatic Transaction (Recommended)

```php
$user = $em->transaction(function($em) {
    $user = new User();
    $user->email = 'test@example.com';
    $em->persist($user);
    $em->flush();
    return $user;
});
// Automatically commits on success, rolls back on exception
```
    
#### Manual Transaction
    
```php
$em->beginTransaction();
try {
    $user = new User();
    $em->persist($user);
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

---

### Migrations

Generate and execute database migrations automatically.

#### Generate Migrations

```php
// Generate for one entity
$sql = $em->generateMigration(User::class);

// Generate for multiple entities
$sql = $em->generateMigrations([User::class, Post::class]);
```

#### CLI Commands

The package includes a ready-to-use CLI script:

```bash
# Generate migration
php bin/doctrine-migrate generate

# Generate for specific entity
php bin/doctrine-migrate generate App\Entity\User

# Execute migrations
php bin/doctrine-migrate migrate

# Rollback last migration
php bin/doctrine-migrate rollback

# Rollback multiple migrations
php bin/doctrine-migrate rollback --steps=3

# Check status
php bin/doctrine-migrate status

# Show help
php bin/doctrine-migrate help
```

#### Configuration

The CLI script automatically detects configuration from:

1. Environment variable `DOCTRINE_CONFIG` (path to PHP file)
2. `config/database.php` (from current directory)
3. `../config/database.php` (from current directory)
4. Environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

**Example `config/database.php`**:

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

#### Migration Rollback

Migrations can be rolled back using the CLI:

```bash
# Rollback last migration
php bin/doctrine-migrate rollback

# Rollback 3 migrations
php bin/doctrine-migrate rollback --steps=3
```

The system supports:
- Automatic rollback generation (CREATE TABLE → DROP TABLE)
- Custom rollback files (`migration_name_down.sql`)
- Migration classes implementing `MigrationInterface` with `down()` method

---

### Performance Features

#### Query Cache

Cache query results to improve performance:

```php
// Enable query cache
$queryCache = new \JulienLinard\Doctrine\Cache\QueryCache(
    defaultTtl: 3600,  // 1 hour
    enabled: true
);

$em = new EntityManager($config, $queryCache);

// Use cache in repositories
$users = $repository->findAll(true, 3600); // Cache for 1 hour
$users = $repository->findBy(
    ['is_active' => true],
    null, null, null,
    true,  // use cache
    3600   // TTL
);

// Cache is automatically invalidated on entity updates
```

#### Batch Operations

Insert multiple entities efficiently:

```php
$users = [];
for ($i = 1; $i <= 1000; $i++) {
    $user = new User();
    $user->email = "user{$i}@example.com";
    $users[] = $user;
}

// Single INSERT query with multiple VALUES
$em->persistBatch($users);
$em->flush();
```

#### N+1 Query Optimization

Eager loading with batch loading prevents N+1 queries:

```php
// Before: 1 query + N queries (N+1 problem)
// After: 1 query + 1 query (optimized)
$users = $repository->findAllWith(['posts']);
```

#### Automatic Indexes

Foreign key columns automatically get indexes for optimal join performance.

---

### Query Logging

Log all SQL queries for debugging and performance analysis:

```php
// Enable query logging
$logger = $em->enableQueryLog(
    enabled: true,
    logFile: 'queries.log',  // Optional: log to file
    logToConsole: true       // Optional: log to console
);

// Execute queries
$user = new User();
$em->persist($user);
$em->flush();

// View logs
$logs = $logger->getLogs();
foreach ($logs as $log) {
    echo $log['sql'] . ' (' . ($log['time'] * 1000) . 'ms)' . PHP_EOL;
    echo 'Params: ' . json_encode($log['params']) . PHP_EOL;
}

// Get statistics
echo "Total queries: " . $logger->count() . PHP_EOL;
echo "Total time: " . ($logger->getTotalTime() * 1000) . "ms" . PHP_EOL;

// Clear logs
$logger->clear();

// Disable logging
$em->disableQueryLog();
```

---

### API Reference

#### EntityManager Methods

| Method | Description |
|--------|-------------|
| `persist(object $entity): void` | Mark entity for persistence |
| `persistBatch(array $entities): void` | Mark multiple entities for batch insert |
| `flush(): void` | Execute pending operations |
| `remove(object $entity): void` | Mark entity for deletion |
| `find(string $entityClass, int\|string $id): ?object` | Find entity by ID |
| `getRepository(string $entityClass): EntityRepository` | Get entity repository |
| `createRepository(string $repositoryClass, string $entityClass): EntityRepository` | Create custom repository |
| `transaction(callable $callback): mixed` | Execute in transaction with auto rollback |
| `beginTransaction(): void` | Start transaction |
| `commit(): void` | Commit transaction |
| `rollback(): void` | Rollback transaction |
| `enableQueryLog(bool $enabled, ?string $logFile, bool $logToConsole): QueryLoggerInterface` | Enable query logging |
| `disableQueryLog(): void` | Disable query logging |
| `getQueryLogger(): ?QueryLoggerInterface` | Get query logger |
| `generateMigration(string $entityClass): string` | Generate migration SQL |
| `generateMigrations(array $entityClasses): string` | Generate migrations for multiple entities |

#### EntityRepository Methods

| Method | Description |
|--------|-------------|
| `find(int\|string $id): ?object` | Find entity by ID |
| `findOrFail(int\|string $id): object` | Find entity by ID or throw exception |
| `findAll(bool $useCache, ?int $cacheTtl): array` | Find all entities |
| `findBy(array $criteria, ?array $orderBy, ?int $limit, ?int $offset, bool $useCache, ?int $cacheTtl): array` | Find entities by criteria |
| `findOneBy(array $criteria): ?object` | Find one entity by criteria |
| `findOneByOrFail(array $criteria): object` | Find one entity or throw exception |
| `findAllWith(array $relations): array` | Find all with eager-loaded relations (optimized) |

---

## 🎯 Best Practices

### Performance

1. **Use batch operations** for multiple inserts:
```php
   $em->persistBatch($entities); // Instead of loop with persist()
```

2. **Use eager loading** to avoid N+1 queries:
```php
   $users = $repository->findAllWith(['posts']); // Optimized
```

3. **Enable query cache** for frequently accessed data:
```php
   $users = $repository->findAll(true, 3600);
```

4. **Use transactions** for multiple operations:
```php
   $em->transaction(function($em) { /* ... */ });
```

### Code Quality

1. **Use `findOrFail()`** instead of checking for null:
```php
   $user = $repository->findOrFail(1); // Throws exception if not found
```

2. **Use custom repositories** for complex queries:
```php
   $userRepo = $em->createRepository(UserRepository::class, User::class);
```

3. **Enable query logging** during development:
```php
   $em->enableQueryLog(true, 'queries.log', true);
```

---

## 🔗 Integration Examples

### With Symfony/Laravel-style Framework

```php
<?php

use JulienLinard\Doctrine\EntityManager;

class UserController
{
    public function __construct(
        private EntityManager $em
    ) {}
    
    public function show(int $id)
    {
        $user = $this->em->getRepository(User::class)->findOrFail($id);
        return ['user' => $user];
    }
    
    public function store(array $data)
    {
        return $this->em->transaction(function($em) use ($data) {
            $user = new User();
            $user->email = $data['email'];
            $user->name = $data['name'];
            $em->persist($user);
            $em->flush();
            return $user;
        });
    }
}
```

---

## 📝 License

MIT License - See the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 💝 Support

If this package is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support development.

---

**Developed with ❤️ by Julien Linard**
