# Query Builder

**Version** : 1.1.8+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le Query Builder permet de construire des requêtes SQL de manière fluide et sécurisée, avec protection automatique contre les injections SQL.

---

## Création d'un Query Builder

```php
$em = new EntityManager($config);
$qb = $em->createQueryBuilder();
```

---

## Méthodes de Base

### SELECT

```php
// Sélection simple
$qb->select('*');

// Sélection de champs spécifiques
$qb->select(['u.id', 'u.name', 'u.email']);

// Sélection avec alias
$qb->select('u.name AS user_name');
```

### FROM

```php
$qb->from(User::class, 'u');
```

**Paramètres** :
- `$entityClass` : Classe de l'entité
- `$alias` : Alias pour la table

### WHERE

```php
// Condition simple
$qb->where('u.isActive = :active', true);

// Plusieurs conditions (AND)
$qb->where('u.isActive = :active', true)
   ->where('u.email = :email', 'john@example.com');

// Condition OR
$qb->orWhere('u.name = :name', 'John');

// Conditions multiples
$qb->where('u.age > :minAge', 18)
   ->where('u.age < :maxAge', 65);
```

### JOIN

```php
// INNER JOIN
$qb->join(Post::class, 'p', 'p.user_id = u.id');

// LEFT JOIN
$qb->leftJoin(Post::class, 'p', 'p.user_id = u.id');

// RIGHT JOIN
$qb->rightJoin(Post::class, 'p', 'p.user_id = u.id');
```

### ORDER BY

```php
// Tri simple
$qb->orderBy('u.name', 'ASC');

// Plusieurs tris
$qb->orderBy('u.name', 'ASC')
   ->orderBy('u.createdAt', 'DESC');
```

### LIMIT et OFFSET

```php
$qb->limit(10)
   ->offset(20);
```

### GROUP BY

```php
$qb->groupBy('u.category_id');
```

### HAVING

```php
$qb->having('COUNT(p.id) > :minPosts', 5);
```

---

## Fonctions d'Agrégation

### COUNT

```php
$qb->count('u.id', 'user_count');
```

### SUM

```php
$qb->sum('p.views', 'total_views');
```

### AVG

```php
$qb->avg('p.views', 'avg_views');
```

### MIN

```php
$qb->min('p.createdAt', 'first_post');
```

### MAX

```php
$qb->max('p.createdAt', 'last_post');
```

---

## Sous-requêtes

### WHERE avec Sous-requête

```php
$qb->whereSubquery('u.id', 'IN', function($subQb) {
    $subQb->select('p.user_id')
          ->from(Post::class, 'p')
          ->where('p.views > :minViews', 100);
});
```

### EXISTS

```php
$qb->whereExists(function($subQb) {
    $subQb->select('1')
          ->from(Post::class, 'p')
          ->where('p.user_id = u.id');
});
```

### NOT EXISTS

```php
$qb->whereNotExists(function($subQb) {
    $subQb->select('1')
          ->from(Post::class, 'p')
          ->where('p.user_id = u.id');
});
```

---

## UNION

### UNION

```php
$qb1 = $em->createQueryBuilder();
$qb1->select('u.id, u.name')
    ->from(User::class, 'u')
    ->where('u.isActive = :active', true);

$qb2 = $em->createQueryBuilder();
$qb2->select('a.id, a.name')
    ->from(Admin::class, 'a')
    ->where('a.isActive = :active', true);

$qb1->union($qb2);
$results = $qb1->getResult();
```

### UNION ALL

```php
$qb1->unionAll($qb2);
```

---

## Exécution

### getSQL()

Génère le SQL sans l'exécuter.

```php
$sql = $qb->select('*')
    ->from(User::class, 'u')
    ->where('u.isActive = :active', true)
    ->getSQL();
```

### getResult()

Exécute la requête et retourne les résultats.

```php
$results = $qb->select('*')
    ->from(User::class, 'u')
    ->where('u.isActive = :active', true)
    ->getResult();
```

### getParameters()

Retourne les paramètres de la requête.

```php
$params = $qb->getParameters();
```

---

## Exemples Complets

### Requête Simple

```php
$qb = $em->createQueryBuilder();
$users = $qb->select('*')
    ->from(User::class, 'u')
    ->where('u.isActive = :active', true)
    ->orderBy('u.name', 'ASC')
    ->limit(10)
    ->getResult();
```

### Requête avec Jointure

```php
$qb = $em->createQueryBuilder();
$results = $qb->select(['u.name', 'p.title', 'p.views'])
    ->from(User::class, 'u')
    ->join(Post::class, 'p', 'p.user_id = u.id')
    ->where('u.isActive = :active', true)
    ->orderBy('p.views', 'DESC')
    ->limit(10)
    ->getResult();
```

### Requête avec Agrégation

```php
$qb = $em->createQueryBuilder();
$results = $qb->select(['u.name', 'COUNT(p.id) AS post_count'])
    ->from(User::class, 'u')
    ->leftJoin(Post::class, 'p', 'p.user_id = u.id')
    ->groupBy('u.id')
    ->having('COUNT(p.id) > :minPosts', 5)
    ->orderBy('post_count', 'DESC')
    ->getResult();
```

### Requête Complexe avec Sous-requête

```php
$qb = $em->createQueryBuilder();
$users = $qb->select('*')
    ->from(User::class, 'u')
    ->where('u.isActive = :active', true)
    ->whereSubquery('u.id', 'IN', function($subQb) {
        $subQb->select('p.user_id')
              ->from(Post::class, 'p')
              ->where('p.views > :minViews', 100)
              ->groupBy('p.user_id')
              ->having('COUNT(p.id) > :minPosts', 10);
    })
    ->getResult();
```

---

## Sécurité

### Protection contre les Injections SQL

Le Query Builder protège automatiquement contre les injections SQL :

1. **Prepared Statements** : Toutes les valeurs sont liées via des paramètres nommés
2. **Validation des Identifiants** : Les noms de colonnes et tables sont validés
3. **Échappement Automatique** : Les identifiants SQL sont échappés

```php
// ✅ Sécurisé
$qb->where('u.email = :email', $userInput);

// ❌ Non sécurisé (ne pas faire)
$qb->where("u.email = '{$userInput}'");
```

---

## Bonnes Pratiques

### 1. Utiliser des Paramètres Nommés

```php
// ✅ Bon
$qb->where('u.email = :email', $email);

// ❌ Éviter
$qb->where("u.email = '{$email}'");
```

### 2. Utiliser des Alias pour les Tables

```php
// ✅ Bon
$qb->from(User::class, 'u')
   ->join(Post::class, 'p', 'p.user_id = u.id');

// ❌ Éviter
$qb->from(User::class, 'users')
   ->join(Post::class, 'posts', 'posts.user_id = users.id');
```

### 3. Limiter les Résultats

```php
// ✅ Bon
$qb->limit(10)->offset(0);

// ❌ Éviter (pour de grands ensembles)
$results = $qb->getResult(); // Peut charger des milliers de lignes
```

---

## Limitations

1. **Types Union** : Non supportés dans les paramètres
2. **Requêtes Natives** : Utiliser `Connection::execute()` pour les requêtes SQL brutes
3. **Transactions** : Gérées via `EntityManager`, pas via Query Builder

---

