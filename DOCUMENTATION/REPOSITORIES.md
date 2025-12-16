# Repositories

**Version** : 1.1.8+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le pattern Repository abstrait l'accès aux données et fournit une interface cohérente pour interagir avec les entités. Chaque entité a un repository par défaut qui peut être étendu pour ajouter des méthodes personnalisées.

---

## Repository par Défaut

### Récupération d'un Repository

```php
$em = new EntityManager($config);
$userRepository = $em->getRepository(User::class);
```

### Méthodes Disponibles

#### `find(mixed $id): ?object`

Trouve une entité par son ID.

```php
$user = $userRepository->find(1);
if ($user !== null) {
    echo $user->name;
}
```

#### `findAll(bool $useCache = false, ?int $cacheTtl = null): array`

Trouve toutes les entités.

```php
$users = $userRepository->findAll();

// Avec cache
$users = $userRepository->findAll(useCache: true, cacheTtl: 3600);
```

#### `findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, bool $useCache = false, ?int $cacheTtl = null): array`

Trouve des entités par critères.

```php
// Recherche simple
$users = $userRepository->findBy(['isActive' => true]);

// Avec tri
$users = $userRepository->findBy(
    ['isActive' => true],
    ['name' => 'ASC']
);

// Avec pagination
$users = $userRepository->findBy(
    ['isActive' => true],
    ['name' => 'ASC'],
    limit: 10,
    offset: 0
);

// Avec cache
$users = $userRepository->findBy(
    ['isActive' => true],
    useCache: true,
    cacheTtl: 1800
);
```

#### `findOneBy(array $criteria): ?object`

Trouve une entité par critères (retourne la première).

```php
$user = $userRepository->findOneBy(['email' => 'john@example.com']);
```

#### `save(object $entity): void`

Sauvegarde une entité (insertion ou mise à jour).

```php
$user = new User();
$user->email = 'john@example.com';
$user->name = 'John Doe';
$userRepository->save($user);
```

#### `delete(object $entity): void`

Supprime une entité.

```php
$userRepository->delete($user);
```

---

## Repository Personnalisé

### Création d'un Repository Personnalisé

```php
<?php

use JulienLinard\Doctrine\Repository\EntityRepository;

class UserRepository extends EntityRepository
{
    /**
     * Trouve un utilisateur par email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
    
    /**
     * Trouve les utilisateurs actifs
     */
    public function findActiveUsers(): array
    {
        return $this->findBy(
            ['isActive' => true],
            ['name' => 'ASC']
        );
    }
    
    /**
     * Trouve les utilisateurs avec pagination
     */
    public function findPaginated(int $page, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->findBy(
            [],
            ['name' => 'ASC'],
            limit: $perPage,
            offset: $offset
        );
    }
    
    /**
     * Compte les utilisateurs actifs
     */
    public function countActiveUsers(): int
    {
        $qb = $this->createQueryBuilder();
        $result = $qb->select('COUNT(*) as count')
            ->from(User::class, 'u')
            ->where('u.isActive = :active', true)
            ->getResult();
        
        return (int) ($result[0]['count'] ?? 0);
    }
}
```

### Enregistrement d'un Repository Personnalisé

```php
$em = new EntityManager($config);

// Méthode 1 : Via createRepository
$userRepository = $em->createRepository(UserRepository::class, User::class);

// Méthode 2 : Via getRepository (si le repository accepte EntityManager)
// Le repository doit avoir un constructeur qui accepte EntityManager
$userRepository = $em->getRepository(User::class);
```

---

## Cache des Requêtes

### Activation du Cache

```php
$cache = new QueryCache(3600, true); // TTL: 1 heure
$em = new EntityManager($config, $cache);
```

### Utilisation du Cache dans les Repositories

```php
// Cache activé pour findAll
$users = $userRepository->findAll(useCache: true, cacheTtl: 3600);

// Cache activé pour findBy
$users = $userRepository->findBy(
    ['isActive' => true],
    useCache: true,
    cacheTtl: 1800
);
```

### Invalidation du Cache

Le cache est automatiquement invalidé lors des opérations de modification (insert, update, delete).

```php
// Le cache est invalidé automatiquement
$user->name = 'New Name';
$userRepository->save($user);
```

---

## Relations

### Chargement des Relations OneToMany

```php
$user = $userRepository->find(1);

// Charger les relations OneToMany
$em->loadRelations($user);

// Ou charger une relation spécifique
$em->loadRelations($user, 'posts');
```

### Recherche avec Relations

```php
// Trouver des utilisateurs avec leurs posts
$users = $userRepository->findBy(['isActive' => true]);
foreach ($users as $user) {
    $em->loadRelations($user, 'posts');
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

---

## Query Builder dans les Repositories

### Création d'un Query Builder

```php
class UserRepository extends EntityRepository
{
    public function findActiveUsersWithPosts(): array
    {
        $qb = $this->createQueryBuilder();
        
        return $qb->select('u.*, p.*')
            ->from(User::class, 'u')
            ->join(Post::class, 'p', 'p.user_id = u.id')
            ->where('u.isActive = :active', true)
            ->orderBy('u.name', 'ASC')
            ->getResult();
    }
}
```

---

## Bonnes Pratiques

### 1. Utiliser des Repositories Personnalisés pour la Logique Métier

```php
// ✅ Bon
class UserRepository extends EntityRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
}

// ❌ Éviter
$user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
```

### 2. Activer le Cache pour les Requêtes Fréquentes

```php
// ✅ Bon
$users = $userRepository->findAll(useCache: true, cacheTtl: 3600);

// ❌ Éviter (si la requête est fréquente)
$users = $userRepository->findAll();
```

### 3. Utiliser la Pagination pour les Grands Ensembles

```php
// ✅ Bon
$users = $userRepository->findBy(
    [],
    ['name' => 'ASC'],
    limit: 20,
    offset: 0
);

// ❌ Éviter
$users = $userRepository->findAll(); // Peut charger des milliers d'entités
```

### 4. Éviter le N+1 Problem

```php
// ✅ Bon
$users = $userRepository->findBy(['isActive' => true]);
foreach ($users as $user) {
    $em->loadRelations($user, 'posts');
}

// ❌ Éviter
$users = $userRepository->findBy(['isActive' => true]);
foreach ($users as $user) {
    // Charge les posts pour chaque utilisateur (N+1)
    $posts = $postRepository->findBy(['user_id' => $user->id]);
}
```

---

## Limitations

1. **Relations Complexes** : Les jointures complexes nécessitent l'utilisation du Query Builder
2. **Cache** : Cache en mémoire uniquement (pas de cache distribué)
3. **Lazy Loading** : Partiellement implémenté (OneToMany uniquement)

---

