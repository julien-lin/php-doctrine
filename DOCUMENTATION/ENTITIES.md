# Entités et Mapping

**Version** : 1.1.8+  
**Date** : 2025-01-15

---

## Vue d'ensemble

Les entités sont des classes PHP qui représentent des tables dans la base de données. Le mapping est défini via des attributs PHP 8, permettant une syntaxe moderne et déclarative.

---

## Définition d'une Entité

### Structure de Base

```php
<?php

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
    
    #[Column(type: 'string', length: 255)]
    public string $name;
}
```

### Attributs Requis

1. **`#[Entity]`** : Définit la classe comme une entité
   - Paramètre `table` : Nom de la table en base de données

2. **`#[Id]`** : Marque une propriété comme identifiant primaire
   - Doit être combiné avec `#[Column]`

3. **`#[Column]`** : Définit le mapping d'une colonne
   - Paramètres : `type`, `length`, `nullable`, `default`, `name`, `autoIncrement`

---

## Types de Colonnes

### Types Supportés

- **`string`** : Chaîne de caractères (VARCHAR, TEXT)
- **`integer`** : Entier (INT, BIGINT)
- **`boolean`** : Booléen (BOOLEAN, TINYINT)
- **`decimal`** : Décimal (DECIMAL, NUMERIC)
- **`float`** : Nombre à virgule flottante (FLOAT, DOUBLE)
- **`datetime`** : Date et heure (DATETIME, TIMESTAMP)
- **`date`** : Date uniquement (DATE)
- **`time`** : Heure uniquement (TIME)
- **`text`** : Texte long (TEXT, LONGTEXT)
- **`json`** : JSON (JSON, TEXT)

### Exemples

```php
#[Column(type: 'string', length: 255)]
public string $email;

#[Column(type: 'integer')]
public int $age;

#[Column(type: 'boolean', default: true)]
public bool $isActive = true;

#[Column(type: 'decimal', length: 10, nullable: true)]
public ?float $price = null;

#[Column(type: 'datetime', nullable: true)]
public ?\DateTime $createdAt = null;

#[Column(type: 'text', nullable: true)]
public ?string $description = null;
```

---

## Propriétés de Colonnes

### `type`
Type de données SQL. Détermine le type de colonne créé.

### `length`
Longueur maximale (pour `string`, `decimal`).

```php
#[Column(type: 'string', length: 100)]
public string $title;
```

### `nullable`
Si `true`, la colonne peut être `NULL`.

```php
#[Column(type: 'string', nullable: true)]
public ?string $middleName = null;
```

### `default`
Valeur par défaut de la colonne.

```php
#[Column(type: 'boolean', default: true)]
public bool $isActive = true;
```

### `name`
Nom de la colonne en base de données (si différent du nom de la propriété).

```php
#[Column(type: 'integer', name: 'user_id')]
public int $userId;
```

### `autoIncrement`
Si `true`, la colonne est auto-incrémentée (pour les IDs).

```php
#[Id]
#[Column(type: 'integer', autoIncrement: true)]
public ?int $id = null;
```

---

## Index

### Définition d'un Index

```php
use JulienLinard\Doctrine\Mapping\Index;

#[Column(type: 'string', length: 255)]
#[Index(name: 'idx_email', unique: true)]
public string $email;
```

### Paramètres

- **`name`** : Nom de l'index (optionnel, généré automatiquement si non spécifié)
- **`unique`** : Si `true`, crée un index unique

---

## Relations

### ManyToOne (Plusieurs vers Un)

Relation où plusieurs entités référencent une seule entité.

```php
use JulienLinard\Doctrine\Mapping\ManyToOne;

#[Entity(table: 'posts')]
class Post
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $title;
    
    #[ManyToOne(
        targetEntity: User::class,
        inversedBy: 'posts',
        joinColumn: 'user_id'
    )]
    public ?User $user = null;
}
```

**Paramètres** :
- **`targetEntity`** : Classe de l'entité cible
- **`inversedBy`** : Nom de la propriété dans l'entité cible (pour relation bidirectionnelle)
- **`joinColumn`** : Nom de la colonne de jointure (clé étrangère)
- **`cascade`** : Opérations en cascade (`persist`, `remove`, `merge`, `refresh`)

### OneToMany (Un vers Plusieurs)

Relation où une entité a plusieurs entités associées.

```php
use JulienLinard\Doctrine\Mapping\OneToMany;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[OneToMany(
        targetEntity: Post::class,
        mappedBy: 'user',
        cascade: ['persist', 'remove']
    )]
    public array $posts = [];
}
```

**Paramètres** :
- **`targetEntity`** : Classe de l'entité cible
- **`mappedBy`** : Nom de la propriété dans l'entité cible qui référence cette entité
- **`cascade`** : Opérations en cascade

**Note** : Les relations OneToMany sont chargées en lazy loading par défaut.

### ManyToMany (Plusieurs vers Plusieurs)

Relation où plusieurs entités sont liées à plusieurs autres entités via une table de jointure.

```php
use JulienLinard\Doctrine\Mapping\ManyToMany;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[ManyToMany(
        targetEntity: Role::class,
        mappedBy: 'users',
        joinTable: 'user_roles'
    )]
    public array $roles = [];
}
```

**Paramètres** :
- **`targetEntity`** : Classe de l'entité cible
- **`mappedBy`** : Nom de la propriété dans l'entité cible (pour relation bidirectionnelle)
- **`joinTable`** : Nom de la table de jointure (optionnel, généré automatiquement)
- **`cascade`** : Opérations en cascade

**Note** : La table de jointure est créée automatiquement lors de la génération de migrations.

---

## Validation

### Attributs de Validation

```php
use JulienLinard\Doctrine\Validation\Assert;

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Assert(type: 'NotBlank')]
    #[Assert(type: 'Email')]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    #[Assert(type: 'NotBlank')]
    #[Assert(type: 'Length', options: ['min' => 3, 'max' => 255])]
    public string $name;
    
    #[Column(type: 'decimal')]
    #[Assert(type: 'Range', options: ['min' => 0, 'max' => 10000])]
    public float $price;
}
```

### Règles de Validation Disponibles

- **`NotBlank`** : La valeur ne doit pas être vide
- **`Email`** : La valeur doit être un email valide
- **`Length`** : Longueur minimale et maximale
- **`Range`** : Valeur dans une plage (min, max)
- **`Regex`** : Correspondance avec une expression régulière

---

## Exemples Complets

### Entité Simple

```php
<?php

use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Doctrine\Validation\Assert;

#[Entity(table: 'products')]
class Product
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    #[Assert(type: 'NotBlank')]
    public string $name;
    
    #[Column(type: 'text', nullable: true)]
    public ?string $description = null;
    
    #[Column(type: 'decimal', length: 10)]
    #[Assert(type: 'Range', options: ['min' => 0])]
    public float $price;
    
    #[Column(type: 'integer', default: 0)]
    public int $stock = 0;
    
    #[Column(type: 'boolean', default: true)]
    public bool $isActive = true;
    
    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTime $createdAt = null;
}
```

### Entité avec Relations

```php
<?php

use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;
use JulienLinard\Doctrine\Mapping\ManyToOne;
use JulienLinard\Doctrine\Mapping\OneToMany;

#[Entity(table: 'categories')]
class Category
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
    
    #[OneToMany(targetEntity: Product::class, mappedBy: 'category')]
    public array $products = [];
}

#[Entity(table: 'products')]
class Product
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
    
    #[ManyToOne(targetEntity: Category::class, inversedBy: 'products', joinColumn: 'category_id')]
    public ?Category $category = null;
}
```

---

## Bonnes Pratiques

### 1. Utiliser des Types Stricts

```php
// ✅ Bon
public string $email;

// ❌ Éviter
public $email;
```

### 2. Définir des Valeurs par Défaut

```php
#[Column(type: 'boolean', default: true)]
public bool $isActive = true;
```

### 3. Utiliser des Types Nullables Appropriés

```php
#[Column(type: 'string', nullable: true)]
public ?string $middleName = null;
```

### 4. Nommer les Colonnes Explicitement si Nécessaire

```php
#[Column(type: 'integer', name: 'user_id')]
public int $userId;
```

### 5. Valider les Données

```php
#[Assert(type: 'NotBlank')]
#[Assert(type: 'Email')]
public string $email;
```

---

## Limitations

1. **Types Union** : Non supportés (utiliser `nullable` à la place)
2. **Héritage** : Non supporté (pas de table par hiérarchie)
3. **Relations Bidirectionnelles Complexes** : Support limité
4. **Types Personnalisés** : Non supportés (utiliser les types standard)

---

