# Architecture du Module doctrine-php

**Version** : 1.1.8+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le module `doctrine-php` est un ORM (Object-Relational Mapping) moderne pour PHP 8+ inspiré de Doctrine ORM. Il fournit un Entity Manager, un système de Repository, un Query Builder, et un système de mapping basé sur les attributs PHP 8.

---

## Architecture Globale

```
┌─────────────────────────────────────────────────────────────┐
│                    EntityManager                            │
│  (Gestionnaire principal du cycle de vie des entités)       │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┴──────────────┐
        │                             │
┌───────▼────────┐          ┌────────▼──────────┐
│   Connection   │          │ MetadataReader    │
│   (PDO)        │          │  (Reflection)     │
└───────┬────────┘          └────────┬──────────┘
        │                             │
        │                    ┌────────▼──────────┐
        │                    │  QueryCache        │
        │                    │  (Cache mémoire)   │
        │                    └────────────────────┘
        │
┌───────▼────────┐
│  Repository    │
│  (Pattern)     │
└───────┬────────┘
        │
┌───────▼────────┐
│ QueryBuilder   │
│  (Fluent API)  │
└────────────────┘
```

---

## Composants Principaux

### 1. EntityManager

**Rôle** : Gestionnaire principal du cycle de vie des entités  
**Pattern** : Unit of Work  
**Responsabilités** :
- Gestion du cycle de vie des entités (persist, remove, flush)
- Gestion des transactions
- Cache des repositories
- Dirty checking (détection des modifications)
- Validation automatique des entités
- Gestion des relations (lazy loading)

**Fichier** : `src/Doctrine/EntityManager.php`

**Méthodes principales** :
- `persist(object $entity): void` : Marque une entité pour persistance
- `remove(object $entity): void` : Marque une entité pour suppression
- `flush(): void` : Exécute les opérations en attente
- `find(string $entityClass, mixed $id): ?object` : Trouve une entité par ID
- `getRepository(string $entityClass): RepositoryInterface` : Récupère un repository
- `beginTransaction(): void` : Démarre une transaction
- `commit(): void` : Valide une transaction
- `rollback(): void` : Annule une transaction

**Exemple** :
```php
$em = new EntityManager($config);

$user = new User();
$user->email = 'john@example.com';
$em->persist($user);
$em->flush();
```

### 2. Connection

**Rôle** : Abstraction de la connexion PDO  
**Pattern** : Adapter Pattern  
**Responsabilités** :
- Gestion de la connexion PDO
- Exécution de requêtes SQL
- Gestion des transactions
- Support multi-DBMS (MySQL, PostgreSQL, SQLite)
- Protection contre les injections SQL (prepared statements)

**Fichier** : `src/Doctrine/Database/Connection.php`

**Fonctionnalités** :
- Connexion automatique à la base de données
- Requêtes préparées par défaut
- Support des transactions
- Logging des requêtes (optionnel)

### 3. MetadataReader

**Rôle** : Lecteur de métadonnées des entités  
**Pattern** : Metadata Mapping  
**Responsabilités** :
- Lecture des attributs PHP 8 (Entity, Column, Id, Relations)
- Cache des métadonnées
- Extraction des informations de mapping
- Détection des relations (OneToMany, ManyToOne, ManyToMany)

**Fichier** : `src/Doctrine/Metadata/MetadataReader.php`

**Métadonnées lues** :
- Nom de la table
- Propriété ID
- Colonnes et leurs types
- Relations (OneToMany, ManyToOne, ManyToMany)
- Index

### 4. Repository

**Rôle** : Accès aux données via le pattern Repository  
**Pattern** : Repository Pattern  
**Responsabilités** :
- CRUD (Create, Read, Update, Delete)
- Requêtes personnalisées
- Cache des requêtes
- Gestion des relations
- Hydratation des entités

**Fichier** : `src/Doctrine/Repository/EntityRepository.php`

**Méthodes principales** :
- `find(mixed $id): ?object` : Trouve une entité par ID
- `findAll(bool $useCache = false): array` : Trouve toutes les entités
- `findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array` : Trouve par critères
- `findOneBy(array $criteria): ?object` : Trouve une entité par critères
- `save(object $entity): void` : Sauvegarde une entité
- `delete(object $entity): void` : Supprime une entité

### 5. QueryBuilder

**Rôle** : Construction fluide de requêtes SQL  
**Pattern** : Builder Pattern  
**Responsabilités** :
- Construction de requêtes SQL de manière fluide
- Protection contre les injections SQL
- Support des jointures
- Support des sous-requêtes (UNION)
- Support des agrégations (GROUP BY, HAVING)

**Fichier** : `src/Doctrine/QueryBuilder/QueryBuilder.php`

**Méthodes principales** :
- `select(string|array $fields): self` : Sélectionne des champs
- `from(string $entityClass, string $alias): self` : Définit la table source
- `where(string $condition, mixed $value = null): self` : Ajoute une condition WHERE
- `join(string $entityClass, string $alias, string $condition): self` : Ajoute une jointure
- `orderBy(string $field, string $direction = 'ASC'): self` : Ajoute un tri
- `limit(int $limit): self` : Limite le nombre de résultats
- `getSQL(): string` : Génère le SQL
- `getResult(): array` : Exécute la requête et retourne les résultats

### 6. QueryCache

**Rôle** : Cache des résultats de requêtes  
**Pattern** : Cache Pattern  
**Responsabilités** :
- Mise en cache des résultats de requêtes
- Gestion du TTL (Time To Live)
- Invalidation du cache
- Génération de clés de cache sécurisées (xxh3/sha256)

**Fichier** : `src/Doctrine/Cache/QueryCache.php`

**Fonctionnalités** :
- Cache en mémoire
- TTL configurable
- Invalidation automatique
- Clés de cache sécurisées (pas de MD5)

### 7. MigrationGenerator

**Rôle** : Génération automatique de migrations  
**Pattern** : Code Generation  
**Responsabilités** :
- Analyse des entités
- Comparaison avec l'état actuel de la base de données
- Génération de CREATE TABLE
- Génération de ALTER TABLE
- Génération de tables de jointure ManyToMany
- Tri topologique des entités par dépendances

**Fichier** : `src/Doctrine/Migration/MigrationGenerator.php`

**Fonctionnalités** :
- Génération automatique de schémas
- Détection des différences
- Gestion des clés étrangères
- Support des relations ManyToMany

---

## Flux d'Exécution

### 1. Initialisation

```php
$config = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'dbname' => 'mydb',
    'user' => 'root',
    'password' => 'password'
];

$em = new EntityManager($config);
```

**Étapes** :
1. Création de la `Connection` (PDO)
2. Création du `MetadataReader`
3. Création du `QueryCache` (optionnel)

### 2. Persistance d'une Entité

```php
$user = new User();
$user->email = 'john@example.com';
$em->persist($user);
$em->flush();
```

**Étapes** :
1. `persist()` : Validation (si activée) → Ajout à `$toPersist[]`
2. `flush()` : 
   - Pour chaque entité dans `$toPersist[]` :
     - Lecture des métadonnées via `MetadataReader`
     - Génération du SQL (INSERT ou UPDATE selon l'ID)
     - Exécution via `Connection`
     - Sauvegarde de l'état original (dirty checking)
   - Vidage de `$toPersist[]`
   - Invalidation du cache

### 3. Recherche d'une Entité

```php
$user = $em->getRepository(User::class)->find(1);
```

**Étapes** :
1. `getRepository()` : Récupération ou création du repository (cache)
2. `find()` :
   - Vérification du cache (si activé)
   - Génération du SQL SELECT
   - Exécution via `Connection`
   - Hydratation de l'entité
   - Mise en cache (si activé)

### 4. Requête avec QueryBuilder

```php
$qb = $em->createQueryBuilder();
$users = $qb->select('*')
    ->from(User::class, 'u')
    ->where('u.email = :email', 'john@example.com')
    ->getResult();
```

**Étapes** :
1. `createQueryBuilder()` : Création d'une instance
2. Construction fluide de la requête
3. `getSQL()` : Génération du SQL
4. `getResult()` : Exécution et hydratation

---

## Design Patterns Utilisés

### 1. Unit of Work
- **Implémentation** : `EntityManager`
- **Objectif** : Gérer les modifications d'entités et les exécuter en une seule transaction

### 2. Repository Pattern
- **Implémentation** : `EntityRepository`
- **Objectif** : Abstraire l'accès aux données

### 3. Metadata Mapping
- **Implémentation** : `MetadataReader`
- **Objectif** : Mapper les entités PHP aux tables SQL

### 4. Builder Pattern
- **Implémentation** : `QueryBuilder`
- **Objectif** : Construction fluide de requêtes SQL

### 5. Adapter Pattern
- **Implémentation** : `Connection`
- **Objectif** : Adapter PDO pour l'ORM

### 6. Cache Pattern
- **Implémentation** : `QueryCache`
- **Objectif** : Optimiser les performances via le cache

---

## Optimisations

### 1. Cache des Métadonnées
- Les métadonnées sont lues une seule fois par classe
- Stockage dans `$metadataCache` du `MetadataReader`

### 2. Cache des Repositories
- Les repositories sont créés une seule fois par classe
- Stockage dans `$repositories` de l'`EntityManager`

### 3. Cache des Requêtes
- Les résultats de requêtes peuvent être mis en cache
- TTL configurable
- Invalidation automatique lors des modifications

### 4. Dirty Checking
- Détection automatique des modifications d'entités
- Seules les colonnes modifiées sont mises à jour
- Stockage de l'état original dans `$originalStates`

### 5. Batch Operations
- Support des opérations par lot
- Réduction du nombre de requêtes SQL

---

## Sécurité

### 1. Protection contre les Injections SQL
- Utilisation systématique de prepared statements
- Validation des identifiants SQL (noms de colonnes, tables)
- Échappement automatique des valeurs

### 2. Hash Sécurisés
- Utilisation de xxh3/sha256 pour les clés de cache (pas de MD5)
- Protection contre les collisions de hash

### 3. Validation des Entités
- Validation automatique avant persistance
- Support des règles de validation personnalisées

---

## Limitations

1. **Lazy Loading** : Partiellement implémenté (OneToMany uniquement)
2. **Cache** : Cache en mémoire uniquement (pas de cache distribué)
3. **Migrations** : Génération uniquement (pas de rollback automatique)
4. **Relations** : Pas de support des relations bidirectionnelles complexes

---

## Extensibilité

### 1. Repositories Personnalisés
```php
class UserRepository extends EntityRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
}
```

### 2. QueryBuilder Personnalisé
```php
$qb = $em->createQueryBuilder();
// Construction fluide de requêtes complexes
```

### 3. Validation Personnalisée
```php
#[Assert\Email]
public string $email;
```

---

## Performance

### Recommandations

1. **Activer le cache** : Pour les requêtes fréquentes
2. **Utiliser les batch operations** : Pour les insertions multiples
3. **Éviter le N+1** : Utiliser les jointures ou le lazy loading
4. **Indexer les colonnes** : Pour améliorer les performances de recherche

### Métriques

- **Cache hit rate** : Surveiller via `QueryCache::count()`
- **Nombre de requêtes** : Utiliser `QueryLogger` pour déboguer
- **Temps d'exécution** : Utiliser les benchmarks intégrés

---

