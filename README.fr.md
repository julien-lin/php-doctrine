# Doctrine PHP - ORM Moderne pour PHP 8+

[🇬🇧 Lire en anglais](README.md) | [🇫🇷 Lire en français](README.fr.md)

[![Version PHP](https://img.shields.io/badge/php-%3E%3D8.0-blue.svg)](https://www.php.net/)
[![Licence](https://img.shields.io/badge/licence-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-164%20passants-brightgreen.svg)](tests/)

Un ORM (Object-Relational Mapping) moderne et léger pour PHP 8+ inspiré de Doctrine ORM. Comprend Entity Manager, Repository Pattern, Query Builder et mapping avec Attributes PHP 8, avec optimisations automatiques.

## ✨ Fonctionnalités

- 🚀 **Entity Manager** - Gestion complète du cycle de vie des entités
- 📦 **Repository Pattern** - Repositories puissants avec méthodes CRUD
- 🔨 **Query Builder** - Construction fluide de requêtes SQL
- 🏷️ **Attributes PHP 8** - Définition moderne d'entités avec attributes
- 🔗 **Relations** - Support OneToMany, ManyToOne, ManyToMany
- 📊 **Migrations** - Système de migrations de schéma avec rollback
- 🔄 **Transactions** - Support complet des transactions avec rollback automatique
- ⚡ **Performance** - Cache de requêtes, opérations batch, optimisation N+1
- 📝 **Logging SQL** - Logging intégré des requêtes SQL pour le débogage
- 🗄️ **Multi-SGBD** - Support MySQL, PostgreSQL, SQLite

## 🚀 Démarrage rapide

### Installation

```bash
composer require julienlinard/doctrine-php
```

**Prérequis** : PHP 8.0+ et extension PDO

### Utilisation de base

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
    public string $name;
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
$user->email = 'jean@example.com';
$user->name = 'Jean Dupont';
$em->persist($user);
$em->flush();

// Récupérer un utilisateur
$user = $em->getRepository(User::class)->find(1);
echo $user->name; // Jean Dupont
```

## 📖 Documentation

### Table des matières

1. [Définition d'entité](#définition-dentité)
2. [Entity Manager](#entity-manager)
3. [Repository](#repository)
4. [Query Builder](#query-builder)
5. [Relations](#relations)
6. [Transactions](#transactions)
7. [Migrations](#migrations)
8. [Fonctionnalités de performance](#fonctionnalités-de-performance)
9. [Logging des requêtes](#logging-des-requêtes)
10. [Référence API](#référence-api)

---

### Définition d'entité

Les entités sont définies avec les attributes PHP 8 :

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

#### Types de colonnes supportés

- `string` / `varchar` - VARCHAR avec longueur optionnelle
- `text` - TEXT
- `integer` / `int` - INT
- `boolean` / `bool` - TINYINT(1) ou BOOLEAN
- `float` / `double` - DOUBLE
- `decimal` - DECIMAL avec précision/échelle
- `datetime` - DATETIME
- `date` - DATE
- `time` - TIME
- `json` - JSON (sérialisation automatique)

---

### Entity Manager

L'Entity Manager est le composant central pour gérer les entités.

#### Opérations de base

```php
$em = new EntityManager($config);

// Créer
$user = new User();
$user->email = 'test@example.com';
$user->name = 'Utilisateur Test';
$em->persist($user);
$em->flush();

// Lire
$user = $em->find(User::class, 1);

// Mettre à jour
$user->name = 'Nom Modifié';
$em->persist($user); // Re-persister l'entité modifiée
$em->flush();

// Supprimer
$em->remove($user);
$em->flush();
```

#### Opérations batch

Insérer plusieurs entités efficacement avec une seule requête :

```php
$users = [];
for ($i = 1; $i <= 100; $i++) {
    $user = new User();
    $user->email = "user{$i}@example.com";
    $user->name = "Utilisateur {$i}";
    $users[] = $user;
}

// Insertion batch (optimisée - une seule requête INSERT)
$em->persistBatch($users);
$em->flush(); // Exécute un INSERT avec plusieurs VALUES
```

#### Transactions

Gestion simplifiée des transactions avec rollback automatique :

```php
// Méthode 1 : Transaction automatique (recommandée)
$result = $em->transaction(function($em) {
    $user = new User();
    $user->email = 'test@example.com';
    $em->persist($user);
    
    $post = new Post();
    $post->title = 'Mon Article';
    $post->user = $user;
    $em->persist($post);
    
    $em->flush();
    return $user; // La valeur retournée est préservée
});

// Méthode 2 : Transaction manuelle
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

Les repositories fournissent des méthodes pratiques pour interroger les entités.

#### Méthodes standards

```php
$repository = $em->getRepository(User::class);

// Trouver par ID
$user = $repository->find(1);

// Trouver tous
$users = $repository->findAll();

// Trouver par critères
$users = $repository->findBy(['is_active' => true]);
$user = $repository->findOneBy(['email' => 'test@example.com']);

// Trouver ou échouer (lève une exception si non trouvé)
$user = $repository->findOrFail(1);
$user = $repository->findOneByOrFail(['email' => 'test@example.com']);
```

#### Requêtes avancées

```php
// Avec tri
$users = $repository->findBy(
    ['is_active' => true],
    ['created_at' => 'DESC']
);

// Avec pagination
$users = $repository->findBy(
    [],
    ['name' => 'ASC'],
    10,  // limite
    0    // offset
);

// Avec cache de requêtes
$users = $repository->findAll(true, 3600); // Cache pour 1 heure
$users = $repository->findBy(
    ['is_active' => true],
    null, null, null,
    true,  // utiliser le cache
    3600   // TTL
);
```

#### Eager Loading (Optimisation N+1)

Charger les relations efficacement avec batch loading :

```php
// Avant : 1 requête + N requêtes (problème N+1)
// Après : 1 requête + 1 requête (optimisé)
$users = $repository->findAllWith(['posts']);

// Chaque utilisateur a maintenant $user->posts chargé
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

#### Repository personnalisé

Créer des repositories personnalisés avec MetadataReader partagé :

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

// Créer le repository personnalisé
$userRepo = $em->createRepository(UserRepository::class, User::class);
$activeUsers = $userRepo->findActiveUsers();
```

---

### Query Builder

Construire des requêtes SQL complexes avec une interface fluide :

```php
$qb = $em->createQueryBuilder();

// Requête de base
$users = $qb->select('u')
    ->from(User::class, 'u')
    ->where('u.email = :email')
    ->andWhere('u.is_active = :active')
    ->setParameter('email', 'test@example.com')
    ->setParameter('active', true)
    ->orderBy('u.created_at', 'DESC')
    ->setMaxResults(10)
    ->getResult();

// Agrégations
$stats = $qb->select('u')
    ->from(User::class, 'u')
    ->count('u.id', 'total')
    ->sum('u.views', 'total_views')
    ->avg('u.rating', 'avg_rating')
    ->groupBy('u.category_id')
    ->having('total > :min')
    ->setParameter('min', 10)
    ->getResult();

// Sous-requêtes
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

// Utilisation
$user = $em->getRepository(User::class)->find(1);

// Charger les relations manuellement
$em->loadRelations($user, 'posts');

// Ou utiliser eager loading (optimisé)
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

**Note** : Des index automatiques sont créés sur les colonnes de clés étrangères pour des performances optimales.

---

### Transactions

#### Transaction automatique (Recommandée)

```php
$user = $em->transaction(function($em) {
    $user = new User();
    $user->email = 'test@example.com';
    $em->persist($user);
    $em->flush();
    return $user;
});
// Commit automatique en cas de succès, rollback en cas d'exception
```
    
#### Transaction manuelle
    
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

Générer et exécuter des migrations de base de données automatiquement.

#### Générer des migrations

```php
// Générer pour une entité
$sql = $em->generateMigration(User::class);

// Générer pour plusieurs entités
$sql = $em->generateMigrations([User::class, Post::class]);
```

#### Commandes CLI

Le package inclut un script CLI prêt à l'emploi :

```bash
# Générer une migration
php bin/doctrine-migrate generate

# Générer pour une entité spécifique
php bin/doctrine-migrate generate App\Entity\User

# Exécuter les migrations
php bin/doctrine-migrate migrate

# Annuler la dernière migration
php bin/doctrine-migrate rollback

# Annuler plusieurs migrations
php bin/doctrine-migrate rollback --steps=3

# Vérifier le statut
php bin/doctrine-migrate status

# Afficher l'aide
php bin/doctrine-migrate help
```

#### Configuration

Le script CLI détecte automatiquement la configuration depuis :

1. Variable d'environnement `DOCTRINE_CONFIG` (chemin vers fichier PHP)
2. `config/database.php` (depuis le répertoire courant)
3. `../config/database.php` (depuis le répertoire courant)
4. Variables d'environnement `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

**Exemple `config/database.php`** :

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

#### Rollback de migrations

Les migrations peuvent être annulées via le CLI :

```bash
# Annuler la dernière migration
php bin/doctrine-migrate rollback

# Annuler 3 migrations
php bin/doctrine-migrate rollback --steps=3
```

Le système supporte :
- Génération automatique de rollback (CREATE TABLE → DROP TABLE)
- Fichiers de rollback personnalisés (`migration_name_down.sql`)
- Classes de migration implémentant `MigrationInterface` avec méthode `down()`

---

### Fonctionnalités de performance

#### Cache de requêtes

Mettre en cache les résultats de requêtes pour améliorer les performances :

```php
// Activer le cache de requêtes
$queryCache = new \JulienLinard\Doctrine\Cache\QueryCache(
    defaultTtl: 3600,  // 1 heure
    enabled: true
);

$em = new EntityManager($config, $queryCache);

// Utiliser le cache dans les repositories
$users = $repository->findAll(true, 3600); // Cache pour 1 heure
$users = $repository->findBy(
    ['is_active' => true],
    null, null, null,
    true,  // utiliser le cache
    3600   // TTL
);

// Le cache est automatiquement invalidé lors des mises à jour d'entités
```

#### Opérations batch

Insérer plusieurs entités efficacement :

```php
$users = [];
for ($i = 1; $i <= 1000; $i++) {
    $user = new User();
    $user->email = "user{$i}@example.com";
    $users[] = $user;
}

// Une seule requête INSERT avec plusieurs VALUES
$em->persistBatch($users);
$em->flush();
```

#### Optimisation N+1

L'eager loading avec batch loading évite les requêtes N+1 :

```php
// Avant : 1 requête + N requêtes (problème N+1)
// Après : 1 requête + 1 requête (optimisé)
$users = $repository->findAllWith(['posts']);
```

#### Index automatiques

Les colonnes de clés étrangères reçoivent automatiquement des index pour des performances optimales lors des jointures.

---

### Logging des requêtes

Logger toutes les requêtes SQL pour le débogage et l'analyse de performance :

```php
// Activer le logging des requêtes
$logger = $em->enableQueryLog(
    enabled: true,
    logFile: 'queries.log',  // Optionnel : logger dans un fichier
    logToConsole: true       // Optionnel : logger dans la console
);

// Exécuter des requêtes
$user = new User();
$em->persist($user);
$em->flush();

// Voir les logs
$logs = $logger->getLogs();
foreach ($logs as $log) {
    echo $log['sql'] . ' (' . ($log['time'] * 1000) . 'ms)' . PHP_EOL;
    echo 'Paramètres : ' . json_encode($log['params']) . PHP_EOL;
}

// Obtenir des statistiques
echo "Total requêtes : " . $logger->count() . PHP_EOL;
echo "Temps total : " . ($logger->getTotalTime() * 1000) . "ms" . PHP_EOL;

// Vider les logs
$logger->clear();

// Désactiver le logging
$em->disableQueryLog();
```

---

### Référence API

#### Méthodes EntityManager

| Méthode | Description |
|---------|-------------|
| `persist(object $entity): void` | Marquer une entité pour persistance |
| `persistBatch(array $entities): void` | Marquer plusieurs entités pour insertion batch |
| `flush(): void` | Exécuter les opérations en attente |
| `remove(object $entity): void` | Marquer une entité pour suppression |
| `find(string $entityClass, int\|string $id): ?object` | Trouver une entité par ID |
| `getRepository(string $entityClass): EntityRepository` | Obtenir le repository d'une entité |
| `createRepository(string $repositoryClass, string $entityClass): EntityRepository` | Créer un repository personnalisé |
| `transaction(callable $callback): mixed` | Exécuter dans une transaction avec rollback automatique |
| `beginTransaction(): void` | Démarrer une transaction |
| `commit(): void` | Valider une transaction |
| `rollback(): void` | Annuler une transaction |
| `enableQueryLog(bool $enabled, ?string $logFile, bool $logToConsole): QueryLoggerInterface` | Activer le logging des requêtes |
| `disableQueryLog(): void` | Désactiver le logging des requêtes |
| `getQueryLogger(): ?QueryLoggerInterface` | Obtenir le logger de requêtes |
| `generateMigration(string $entityClass): string` | Générer une migration SQL |
| `generateMigrations(array $entityClasses): string` | Générer des migrations pour plusieurs entités |

#### Méthodes EntityRepository

| Méthode | Description |
|---------|-------------|
| `find(int\|string $id): ?object` | Trouver une entité par ID |
| `findOrFail(int\|string $id): object` | Trouver une entité par ID ou lever une exception |
| `findAll(bool $useCache, ?int $cacheTtl): array` | Trouver toutes les entités |
| `findBy(array $criteria, ?array $orderBy, ?int $limit, ?int $offset, bool $useCache, ?int $cacheTtl): array` | Trouver des entités par critères |
| `findOneBy(array $criteria): ?object` | Trouver une entité par critères |
| `findOneByOrFail(array $criteria): object` | Trouver une entité ou lever une exception |
| `findAllWith(array $relations): array` | Trouver toutes les entités avec relations eager-loaded (optimisé) |

---

## 🎯 Bonnes pratiques

### Performance

1. **Utiliser les opérations batch** pour plusieurs insertions :
```php
   $em->persistBatch($entities); // Au lieu d'une boucle avec persist()
```

2. **Utiliser l'eager loading** pour éviter les requêtes N+1 :
```php
   $users = $repository->findAllWith(['posts']); // Optimisé
```

3. **Activer le cache de requêtes** pour les données fréquemment accédées :
```php
   $users = $repository->findAll(true, 3600);
```

4. **Utiliser les transactions** pour plusieurs opérations :
```php
   $em->transaction(function($em) { /* ... */ });
```

### Qualité du code

1. **Utiliser `findOrFail()`** au lieu de vérifier null :
```php
   $user = $repository->findOrFail(1); // Lève une exception si non trouvé
```

2. **Utiliser des repositories personnalisés** pour les requêtes complexes :
```php
   $userRepo = $em->createRepository(UserRepository::class, User::class);
```

3. **Activer le logging des requêtes** pendant le développement :
```php
   $em->enableQueryLog(true, 'queries.log', true);
```

---

## 🔗 Exemples d'intégration

### Avec un framework style Symfony/Laravel

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

## 📝 Licence

Licence MIT - Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à soumettre une Pull Request.

## 💝 Soutien

Si ce package vous est utile, envisagez de [devenir un sponsor](https://github.com/sponsors/julien-lin) pour soutenir le développement.

---

**Développé avec ❤️ par Julien Linard**
