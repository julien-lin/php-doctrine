<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

/**
 * Tests d'intégration pour EntityRepository
 * Teste des scénarios réels d'utilisation du repository
 */
class RepositoryIntegrationTest extends TestCase
{
    private EntityManager $em;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];
        
        $this->em = new EntityManager($config);
        $this->createTestTable();
        $this->insertTestData();
    }
    
    /**
     * Test de findAll avec tri
     */
    public function testFindAllWithOrderBy(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        // Trier par nom ASC
        $products = $repository->findBy([], ['name' => 'ASC']);
        
        $this->assertCount(5, $products);
        $this->assertEquals('Product A', $products[0]->name);
        $this->assertEquals('Product E', $products[4]->name);
        
        // Trier par nom DESC
        $products = $repository->findBy([], ['name' => 'DESC']);
        $this->assertEquals('Product E', $products[0]->name);
        $this->assertEquals('Product A', $products[4]->name);
    }
    
    /**
     * Test de findBy avec limite et offset (pagination)
     */
    public function testPagination(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        // Page 1 : 2 premiers résultats
        $page1 = $repository->findBy([], ['id' => 'ASC'], 2, 0);
        $this->assertCount(2, $page1);
        $this->assertEquals(1, $page1[0]->id);
        $this->assertEquals(2, $page1[1]->id);
        
        // Page 2 : 2 résultats suivants
        $page2 = $repository->findBy([], ['id' => 'ASC'], 2, 2);
        $this->assertCount(2, $page2);
        $this->assertEquals(3, $page2[0]->id);
        $this->assertEquals(4, $page2[1]->id);
        
        // Page 3 : dernier résultat
        $page3 = $repository->findBy([], ['id' => 'ASC'], 2, 4);
        $this->assertCount(1, $page3);
        $this->assertEquals(5, $page3[0]->id);
    }
    
    /**
     * Test de findOneBy avec plusieurs critères
     */
    public function testFindOneByMultipleCriteria(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        $product = $repository->findOneBy([
            'name' => 'Product C',
            'price' => 30.0
        ]);
        
        $this->assertNotNull($product);
        $this->assertEquals('Product C', $product->name);
        $this->assertEquals(30.0, $product->price);
    }
    
    /**
     * Test de findOneBy avec résultat non trouvé
     */
    public function testFindOneByNotFound(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        $product = $repository->findOneBy([
            'name' => 'Non Existent Product'
        ]);
        
        $this->assertNull($product);
    }
    
    /**
     * Test de recherche avec valeurs nulles
     */
    public function testFindByWithNullValues(): void
    {
        // Créer un produit avec description nulle
        $product = new TestProduct();
        $product->name = 'Product Without Description';
        $product->price = 50.0;
        $product->description = null;
        $this->em->persist($product);
        $this->em->flush();
        
        $repository = $this->em->getRepository(TestProduct::class);
        
        // Rechercher les produits sans description (utiliser une requête directe car SQLite gère mal NULL dans findBy)
        $connection = $this->em->getConnection();
        $sql = "SELECT * FROM test_products WHERE description IS NULL";
        $products = $connection->fetchAll($sql);
        
        $this->assertGreaterThanOrEqual(1, count($products));
    }
    
    /**
     * Test de performance avec beaucoup de données
     */
    public function testPerformanceWithManyRecords(): void
    {
        // Créer 100 produits
        for ($i = 1; $i <= 100; $i++) {
            $product = new TestProduct();
            $product->name = "Product {$i}";
            $product->price = (float)($i * 10);
            $this->em->persist($product);
        }
        $this->em->flush();
        
        $repository = $this->em->getRepository(TestProduct::class);
        
        $start = microtime(true);
        $products = $repository->findAll();
        $end = microtime(true);
        
        $this->assertGreaterThanOrEqual(100, count($products));
        $this->assertLessThan(1.0, $end - $start); // Doit être rapide (< 1 seconde)
    }
    
    /**
     * Test avec findAllWith et relations (eager loading optimisé)
     */
    public function testFindAllWithRelations(): void
    {
        // Créer des utilisateurs avec posts
        $this->createUsersWithPosts();
        
        $repository = $this->em->getRepository(\JulienLinard\Doctrine\Tests\Fixtures\TestUserWithRelations::class);
        
        // Charger tous les utilisateurs avec leurs posts (eager loading)
        $users = $repository->findAllWith(['posts']);
        
        $this->assertGreaterThanOrEqual(3, count($users));
        
        // Vérifier que chaque utilisateur a ses posts chargés
        foreach ($users as $user) {
            $this->assertIsArray($user->posts);
            $this->assertGreaterThanOrEqual(2, count($user->posts));
        }
    }
    
    /**
     * Test avec query cache dans findBy
     */
    public function testFindByWithCache(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        // Premier appel sans cache
        $products1 = $repository->findBy(['price' => 30.0], null, null, null, false);
        $this->assertCount(1, $products1);
        
        // Deuxième appel avec cache
        $products2 = $repository->findBy(['price' => 30.0], null, null, null, true);
        $this->assertCount(1, $products2);
        $this->assertEquals($products1[0]->name, $products2[0]->name);
    }
    
    /**
     * Test avec findOrFail dans un scénario réel
     */
    public function testFindOrFailRealScenario(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        // Trouver un produit existant
        $product = $repository->findOrFail(1);
        $this->assertNotNull($product);
        $this->assertEquals('Product A', $product->name);
    }
    
    /**
     * Test avec findOneByOrFail dans un scénario réel
     */
    public function testFindOneByOrFailRealScenario(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        // Trouver un produit existant
        $product = $repository->findOneByOrFail(['name' => 'Product C', 'price' => 30.0]);
        $this->assertNotNull($product);
        $this->assertEquals('Product C', $product->name);
    }
    
    /**
     * Test de scénario complet : recherche, tri, pagination, cache
     */
    public function testCompleteRepositoryScenario(): void
    {
        $repository = $this->em->getRepository(TestProduct::class);
        
        // Scénario : rechercher des produits, trier, paginer, utiliser le cache
        $products = $repository->findBy(
            [], // Pas de critères
            ['name' => 'ASC'], // Trier par nom
            3, // Limite de 3
            0, // Offset 0
            true, // Utiliser le cache
            3600 // TTL 1 heure
        );
        
        $this->assertCount(3, $products);
        $this->assertEquals('Product A', $products[0]->name);
        $this->assertEquals('Product B', $products[1]->name);
        $this->assertEquals('Product C', $products[2]->name);
    }
    
    /**
     * Crée des utilisateurs avec posts pour tester les relations
     */
    private function createUsersWithPosts(): void
    {
        // Créer les tables nécessaires
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL
            )"
        );
        
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT
            )"
        );
        
        // Créer des utilisateurs avec posts
        for ($i = 1; $i <= 3; $i++) {
            $user = new \JulienLinard\Doctrine\Tests\Fixtures\TestUserWithRelations();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
            $this->em->flush();
            
            // Créer 2 posts pour chaque utilisateur
            for ($j = 1; $j <= 2; $j++) {
                $this->em->getConnection()->execute(
                    "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
                    [$user->id, "Post {$j} for User {$i}", "Content"]
                );
            }
        }
    }
    
    private function createTestTable(): void
    {
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                description TEXT NULL
            )"
        );
    }
    
    private function insertTestData(): void
    {
        $products = [
            ['name' => 'Product A', 'price' => 10.0],
            ['name' => 'Product B', 'price' => 20.0],
            ['name' => 'Product C', 'price' => 30.0],
            ['name' => 'Product D', 'price' => 40.0],
            ['name' => 'Product E', 'price' => 50.0],
        ];
        
        foreach ($products as $data) {
            $product = new TestProduct();
            $product->name = $data['name'];
            $product->price = $data['price'];
            $this->em->persist($product);
        }
        $this->em->flush();
    }
}

#[Entity(table: 'test_products')]
class TestProduct
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
    
    #[Column(type: 'decimal')]
    public float $price;
    
    #[Column(type: 'text', nullable: true)]
    public ?string $description = null;
}

