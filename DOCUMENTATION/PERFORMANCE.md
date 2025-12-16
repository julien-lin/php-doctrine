# Performance et Optimisations

**Version** : 1.1.8+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le module `doctrine-php` inclut plusieurs optimisations pour améliorer les performances : cache des requêtes, dirty checking, batch operations, et lazy loading.

---

## Cache des Requêtes

### Configuration

```php
use JulienLinard\Doctrine\Cache\QueryCache;

// Cache avec TTL de 1 heure
$cache = new QueryCache(3600, true);
$em = new EntityManager($config, $cache);
```

### Utilisation

```php
// Activer le cache pour une requête
$users = $repository->findAll(useCache: true, cacheTtl: 3600);

// Activer le cache pour findBy
$users = $repository->findBy(
    ['isActive' => true],
    useCache: true,
    cacheTtl: 1800
);
```

### Invalidation

Le cache est automatiquement invalidé lors des opérations de modification :

- **INSERT** : Invalidation globale
- **UPDATE** : Invalidation globale
- **DELETE** : Invalidation globale

```php
// Le cache est invalidé automatiquement
$user->name = 'New Name';
$em->persist($user);
$em->flush();
```

### Clés de Cache Sécurisées

Les clés de cache utilisent xxh3/sha256 (pas de MD5) pour la sécurité :

```php
// Génération de clé sécurisée
$cacheKey = $queryCache->generateKey($sql, $params);
// Format: query_<hash_16_chars>
```

---

## Dirty Checking

### Principe

Le dirty checking détecte automatiquement les modifications d'entités et ne met à jour que les colonnes modifiées.

### Fonctionnement

1. **Sauvegarde de l'état original** : Lors du chargement d'une entité, son état est sauvegardé
2. **Comparaison** : Lors du `flush()`, l'état actuel est comparé à l'état original
3. **Mise à jour optimisée** : Seules les colonnes modifiées sont mises à jour

### Exemple

```php
$user = $em->find(User::class, 1);
// État original sauvegardé automatiquement

$user->name = 'New Name';
// Seule la colonne 'name' sera mise à jour

$em->flush();
// SQL généré: UPDATE users SET name = :name WHERE id = :id
// (pas de mise à jour des autres colonnes)
```

### Avantages

- **Réduction du trafic SQL** : Moins de données envoyées à la base
- **Performance** : Moins de colonnes à comparer et mettre à jour
- **Sécurité** : Évite les écrasements accidentels

---

## Batch Operations

### Principe

Les batch operations permettent d'insérer plusieurs entités en une seule transaction, réduisant le nombre de requêtes SQL.

### Utilisation

```php
// Méthode 1 : Via persist() multiple
for ($i = 0; $i < 100; $i++) {
    $user = new User();
    $user->email = "user{$i}@example.com";
    $user->name = "User {$i}";
    $em->persist($user);
}
$em->flush(); // Une seule transaction

// Méthode 2 : Via batchPersist()
$users = [];
for ($i = 0; $i < 100; $i++) {
    $user = new User();
    $user->email = "user{$i}@example.com";
    $user->name = "User {$i}";
    $users[] = $user;
}
$em->batchPersist($users);
$em->flush();
```

### Avantages

- **Performance** : Réduction du nombre de transactions
- **Atomicité** : Toutes les insertions dans une seule transaction
- **Gestion des erreurs** : Rollback automatique en cas d'erreur

---

## Lazy Loading

### Principe

Le lazy loading charge les relations uniquement lorsqu'elles sont accédées, évitant le chargement inutile de données.

### Relations OneToMany

```php
$user = $em->find(User::class, 1);
// Les posts ne sont pas chargés

// Charger les relations explicitement
$em->loadRelations($user, 'posts');
// Les posts sont maintenant chargés

foreach ($user->posts as $post) {
    echo $post->title;
}
```

### Relations ManyToOne

Les relations ManyToOne sont chargées automatiquement lors de l'hydratation.

### Relations ManyToMany

Les relations ManyToMany nécessitent un chargement explicite.

---

## Optimisation des Requêtes

### Éviter le N+1 Problem

**Problème** :

```php
// ❌ N+1 queries
$users = $repository->findAll();
foreach ($users as $user) {
    $posts = $postRepository->findBy(['user_id' => $user->id]);
    // 1 requête pour les users + N requêtes pour les posts
}
```

**Solution** :

```php
// ✅ 2 queries
$users = $repository->findAll();
foreach ($users as $user) {
    $em->loadRelations($user, 'posts');
    // Les posts sont chargés en batch
}
```

### Utiliser les Jointures

```php
// ✅ 1 query avec jointure
$qb = $em->createQueryBuilder();
$results = $qb->select(['u.*', 'p.*'])
    ->from(User::class, 'u')
    ->join(Post::class, 'p', 'p.user_id = u.id')
    ->getResult();
```

---

## Index

### Définition d'Index

```php
#[Column(type: 'string', length: 255)]
#[Index(name: 'idx_email', unique: true)]
public string $email;
```

### Index Automatiques

- **Clé primaire** : Index automatique
- **Clés étrangères** : Index automatique
- **Index personnalisés** : Via l'attribut `#[Index]`

### Recommandations

1. **Indexer les colonnes fréquemment recherchées** : `email`, `username`, etc.
2. **Indexer les colonnes de jointure** : `user_id`, `category_id`, etc.
3. **Éviter les index inutiles** : Trop d'index ralentit les insertions

---

## Pagination

### Utilisation

```php
// Pagination simple
$users = $repository->findBy(
    [],
    ['name' => 'ASC'],
    limit: 20,
    offset: 0
);

// Pagination avec page
function findPaginated(int $page, int $perPage = 20): array
{
    $offset = ($page - 1) * $perPage;
    return $this->findBy(
        [],
        ['name' => 'ASC'],
        limit: $perPage,
        offset: $offset
    );
}
```

### Avantages

- **Réduction de la mémoire** : Moins d'entités chargées
- **Performance** : Requêtes plus rapides
- **Expérience utilisateur** : Chargement plus rapide

---

## Métriques et Monitoring

### Query Logger

```php
use JulienLinard\Doctrine\Database\SimpleQueryLogger;

$logger = new SimpleQueryLogger();
$connection->setQueryLogger($logger);

// Exécuter des requêtes
$users = $repository->findAll();

// Récupérer les logs
$queries = $logger->getQueries();
foreach ($queries as $query) {
    echo $query['sql'] . "\n";
    echo "Time: " . $query['time'] . "ms\n";
}
```

### Cache Statistics

```php
// Nombre d'entrées dans le cache
$count = $queryCache->count();

// Vérifier si le cache est activé
$enabled = $queryCache->isEnabled();
```

---

## Recommandations

### 1. Activer le Cache pour les Requêtes Fréquentes

```php
// ✅ Bon
$users = $repository->findAll(useCache: true, cacheTtl: 3600);

// ❌ Éviter (si la requête est fréquente)
$users = $repository->findAll();
```

### 2. Utiliser la Pagination pour les Grands Ensembles

```php
// ✅ Bon
$users = $repository->findBy([], limit: 20, offset: 0);

// ❌ Éviter
$users = $repository->findAll(); // Peut charger des milliers d'entités
```

### 3. Éviter le N+1 Problem

```php
// ✅ Bon
$users = $repository->findAll();
foreach ($users as $user) {
    $em->loadRelations($user, 'posts');
}

// ❌ Éviter
foreach ($users as $user) {
    $posts = $postRepository->findBy(['user_id' => $user->id]);
}
```

### 4. Utiliser les Batch Operations pour les Insertions Multiples

```php
// ✅ Bon
$users = [];
for ($i = 0; $i < 100; $i++) {
    $users[] = new User();
}
$em->batchPersist($users);
$em->flush();

// ❌ Éviter
for ($i = 0; $i < 100; $i++) {
    $user = new User();
    $em->persist($user);
    $em->flush(); // 100 transactions
}
```

---

## Limitations

1. **Cache en Mémoire** : Pas de cache distribué (Redis, Memcached)
2. **Lazy Loading** : Partiellement implémenté (OneToMany uniquement)
3. **Query Cache** : Invalidation globale (pas d'invalidation granulaire)

---

