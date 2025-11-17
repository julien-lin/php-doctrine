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

### Migrations

Le système de migrations permet de générer automatiquement les migrations SQL à partir de vos entités Doctrine.

#### Génération d'une migration

```php
use JulienLinard\Doctrine\EntityManager;
use App\Entity\User;
use App\Entity\Todo;

$em = new EntityManager($config);

// Générer une migration pour une entité
$sql = $em->generateMigration(User::class);
echo $sql;

// Générer des migrations pour plusieurs entités
$sql = $em->generateMigrations([User::class, Todo::class]);
```

#### Exécution d'une migration

```php
use JulienLinard\Doctrine\EntityManager;

$em = new EntityManager($config);
$runner = $em->getMigrationRunner();
$manager = $em->getMigrationManager();

// Générer le nom de la migration
$migrationName = $manager->generateMigrationName();

// Exécuter la migration
$sql = $em->generateMigration(User::class);
if (!empty($sql)) {
    $runner->run($sql);
    $manager->markAsExecuted($migrationName);
    echo "Migration {$migrationName} appliquée avec succès.\n";
}
```

#### Vérifier les migrations appliquées

```php
$manager = $em->getMigrationManager();
$executed = $manager->getExecutedMigrations();

foreach ($executed as $migration) {
    echo "✅ {$migration}\n";
}
```

#### Script CLI intégré (recommandé)

Le package inclut un script CLI prêt à l'emploi qui détecte automatiquement votre configuration de base de données.

**Utilisation directe depuis le package** :

```bash
# Depuis votre projet (après installation via composer)
php vendor/julienlinard/doctrine-php/bin/doctrine-migrate generate
php vendor/julienlinard/doctrine-php/bin/doctrine-migrate migrate
php vendor/julienlinard/doctrine-php/bin/doctrine-migrate status
```

**Ou via Composer** :

```bash
composer exec doctrine-migrate generate
composer exec doctrine-migrate migrate
composer exec doctrine-migrate status
```

**Créer un lien symbolique (recommandé)** :

```bash
# Créer un lien symbolique dans votre projet
ln -s vendor/julienlinard/doctrine-php/bin/doctrine-migrate bin/doctrine-migrate

# Puis utiliser directement
php bin/doctrine-migrate generate
php bin/doctrine-migrate migrate
php bin/doctrine-migrate status
```

**Configuration automatique** :

Le script cherche automatiquement la configuration dans cet ordre :

1. Variable d'environnement `DOCTRINE_CONFIG` (chemin vers fichier PHP)
2. `config/database.php` (depuis le répertoire courant)
3. `../config/database.php` (depuis le répertoire courant)
4. Variables d'environnement `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

**Exemple de fichier `config/database.php`** :

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

**Commandes disponibles** :

- `generate [EntityClass]` - Génère une migration pour une entité ou toutes les entités
- `migrate` - Exécute les migrations en attente
- `status` - Affiche le statut des migrations
- `help` - Affiche l'aide

#### Script CLI personnalisé (optionnel)

Si vous préférez créer votre propre script CLI personnalisé :

**Créer `bin/migrate.php` dans votre application** :

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use JulienLinard\Doctrine\EntityManager;

// Charger la configuration
$config = require __DIR__ . '/../config/database.php';
$em = new EntityManager($config);

// Récupérer l'action depuis les arguments CLI
$action = $argv[1] ?? 'status';
$entityClass = $argv[2] ?? null;

try {
    match ($action) {
        'generate' => generateMigration($em, $entityClass),
        'migrate' => executeMigrations($em),
        'status' => showStatus($em),
        default => throw new \InvalidArgumentException(
            "Action inconnue : {$action}. Utilisez 'generate', 'migrate' ou 'status'"
        )
    };
} catch (\Exception $e) {
    echo "❌ Erreur : {$e->getMessage()}\n";
    exit(1);
}

function generateMigration(EntityManager $em, ?string $entityClass): void
{
    echo "🔍 Génération de la migration...\n\n";
    
    if ($entityClass) {
        $sql = $em->generateMigration($entityClass);
        if (empty($sql)) {
            echo "✅ Aucune migration nécessaire.\n";
            return;
        }
        echo "📄 Migration SQL :\n" . $sql . "\n";
    } else {
        // Générer pour toutes les entités
        $entities = [/* vos classes d'entités */];
        $sql = $em->generateMigrations($entities);
        if (!empty($sql)) {
            $manager = $em->getMigrationManager();
            $migrationName = $manager->generateMigrationName();
            $filename = __DIR__ . '/../migrations/' . $migrationName . '.sql';
            file_put_contents($filename, $sql);
            echo "💾 Migration sauvegardée : {$filename}\n";
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
            echo "▶️  Exécution de {$migrationName}...\n";
            $sql = file_get_contents($file);
            $runner->run($sql);
            $manager->markAsExecuted($migrationName);
            echo "✅ Migration appliquée.\n";
        }
    }
}

function showStatus(EntityManager $em): void
{
    $manager = $em->getMigrationManager();
    $executed = $manager->getExecutedMigrations();
    
    echo "📊 Migrations appliquées : " . count($executed) . "\n";
    foreach ($executed as $migration) {
        echo "  ✅ {$migration}\n";
    }
}
```

**Rendre le script exécutable** :
```bash
chmod +x bin/migrate.php
```

**Utilisation** :
```bash
php bin/migrate.php generate          # Génère une migration
php bin/migrate.php generate App\Entity\User  # Pour une entité spécifique
php bin/migrate.php migrate            # Exécute les migrations
php bin/migrate.php status             # Affiche le statut
```

> **Note** : `symfony/console` est optionnel et suggéré uniquement si vous souhaitez créer des commandes CLI plus structurées avec validation d'arguments, options, etc. Pour un usage simple, un script PHP natif suffit largement.

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

#### `generateMigration(string $entityClass): string`

Génère une migration SQL pour une entité.

```php
$sql = $em->generateMigration(User::class);
```

#### `generateMigrations(array $entityClasses): string`

Génère des migrations SQL pour plusieurs entités.

```php
$sql = $em->generateMigrations([User::class, Post::class]);
```

#### `getMigrationManager(): MigrationManager`

Retourne le gestionnaire de migrations.

```php
$manager = $em->getMigrationManager();
$migrationName = $manager->generateMigrationName();
$executed = $manager->getExecutedMigrations();
```

#### `getMigrationRunner(): MigrationRunner`

Retourne l'exécuteur de migrations.

```php
$runner = $em->getMigrationRunner();
$runner->run($sql);
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

