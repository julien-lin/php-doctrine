# Migrations

**Version** : 1.1.8+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Le système de migrations permet de générer automatiquement les schémas SQL à partir des entités et de gérer l'évolution de la base de données.

---

## Génération de Migrations

### Pour une Entité

```php
use JulienLinard\Doctrine\Migration\MigrationGenerator;
use JulienLinard\Doctrine\Database\Connection;
use JulienLinard\Doctrine\Metadata\MetadataReader;

$connection = new Connection($config);
$metadataReader = new MetadataReader();
$generator = new MigrationGenerator($connection, $metadataReader);

$sql = $generator->generateForEntity(User::class);
echo $sql;
```

### Pour Plusieurs Entités

```php
$sql = $generator->generateForEntities([
    User::class,
    Post::class,
    Category::class
]);
```

**Note** : Les entités sont automatiquement triées par ordre de dépendance (tri topologique) pour respecter les contraintes de clés étrangères.

---

## Structure des Migrations

### CREATE TABLE

```sql
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### ALTER TABLE

Les migrations détectent automatiquement les différences entre les entités et l'état actuel de la base de données :

```sql
ALTER TABLE `users` 
    ADD COLUMN `middle_name` VARCHAR(255) NULL,
    MODIFY COLUMN `email` VARCHAR(100) NOT NULL;
```

### Tables de Jointure ManyToMany

Les tables de jointure pour les relations ManyToMany sont générées automatiquement :

```sql
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id` INT NOT NULL,
    `role_id` INT NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Gestion des Migrations

### MigrationManager

Le `MigrationManager` gère l'historique des migrations appliquées.

```php
use JulienLinard\Doctrine\Migration\MigrationManager;

$manager = new MigrationManager($connection);
```

### Enregistrer une Migration

```php
$manager->recordMigration('20250115_create_users_table');
```

### Vérifier si une Migration a été Appliquée

```php
if ($manager->hasMigration('20250115_create_users_table')) {
    echo "Migration déjà appliquée";
}
```

### Rollback d'une Migration

```php
$manager->rollbackMigration('20250115_create_users_table');
```

---

## MigrationRunner

Le `MigrationRunner` exécute les migrations et gère les rollbacks.

```php
use JulienLinard\Doctrine\Migration\MigrationRunner;

$runner = new MigrationRunner($connection, $metadataReader);
```

### Exécuter une Migration

```php
$sql = $generator->generateForEntity(User::class);
$runner->run($sql, '20250115_create_users_table');
```

### Rollback

```php
$runner->rollback('20250115_create_users_table');
```

---

## CLI - Doctrine Migrate

### Installation

Le CLI est disponible via Composer :

```bash
composer doctrine-migrate
```

### Commandes Disponibles

#### Créer la Base de Données

```bash
composer migrate:create
```

#### Supprimer la Base de Données

```bash
composer migrate:drop
```

#### Générer une Migration

```bash
php bin/doctrine-migrate generate User Post Category
```

#### Exécuter une Migration

```bash
php bin/doctrine-migrate migrate
```

---

## Tri Topologique des Entités

Les entités sont automatiquement triées par ordre de dépendance pour respecter les contraintes de clés étrangères :

1. **Entités sans dépendances** : Créées en premier
2. **Entités avec dépendances ManyToOne** : Créées après leurs dépendances
3. **Tables de jointure ManyToMany** : Créées en dernier

**Exemple** :

```php
// User n'a pas de dépendances
#[Entity(table: 'users')]
class User { ... }

// Post dépend de User
#[Entity(table: 'posts')]
class Post {
    #[ManyToOne(targetEntity: User::class, joinColumn: 'user_id')]
    public ?User $user = null;
}

// L'ordre de génération sera : User, Post
```

---

## Gestion des Clés Étrangères

### Vérification de l'Existence

Les migrations vérifient si la table cible existe avant de créer une clé étrangère :

1. Vérification si la table existe déjà en base de données
2. Vérification si la table sera créée avant (grâce au tri topologique)
3. Création de la FK uniquement si l'une de ces conditions est remplie

### Exemple

```sql
-- Si la table users existe déjà
CREATE TABLE `posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
);

-- Si la table users sera créée avant (tri topologique)
-- La FK sera créée après la création de users
```

---

## Bonnes Pratiques

### 1. Nommer les Migrations de Manière Descriptive

```php
// ✅ Bon
$manager->recordMigration('20250115_create_users_table');

// ❌ Éviter
$manager->recordMigration('migration_1');
```

### 2. Tester les Migrations sur un Environnement de Développement

```php
// Toujours tester avant de déployer en production
$sql = $generator->generateForEntity(User::class);
// Vérifier le SQL généré
echo $sql;
```

### 3. Utiliser des Transactions pour les Migrations

```php
$em->beginTransaction();
try {
    $sql = $generator->generateForEntity(User::class);
    $connection->execute($sql);
    $manager->recordMigration('20250115_create_users_table');
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

---

## Limitations

1. **Rollback Automatique** : Non implémenté (doit être fait manuellement)
2. **Modifications de Colonnes** : Certaines modifications complexes peuvent nécessiter des migrations manuelles
3. **Données de Test** : Les migrations ne gèrent pas les données de test (seeds)

---

