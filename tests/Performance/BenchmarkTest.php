<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests\Performance;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Mapping\Entity;
use JulienLinard\Doctrine\Mapping\Column;
use JulienLinard\Doctrine\Mapping\Id;

/**
 * Tests de performance (benchmarks)
 * Ces tests mesurent les performances de différentes opérations
 */
class BenchmarkTest extends TestCase
{
    private EntityManager $em;
    private const ITERATIONS = 100;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];
        
        $this->em = new EntityManager($config);
        $this->createTestTable();
    }
    
    /**
     * Benchmark : Insertion de nombreuses entités
     */
    public function testBenchmarkBulkInsert(): void
    {
        $start = microtime(true);
        
        for ($i = 1; $i <= self::ITERATIONS; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
        }
        $this->em->flush();
        
        $end = microtime(true);
        $duration = $end - $start;
        
        // Vérifier que toutes les entités ont été créées
        $repository = $this->em->getRepository(TestUser::class);
        $count = count($repository->findAll());
        $this->assertEquals(self::ITERATIONS, $count);
        
        // Afficher les résultats (optionnel)
        $this->assertLessThan(5.0, $duration, "L'insertion de " . self::ITERATIONS . " entités devrait prendre moins de 5 secondes");
    }
    
    /**
     * Benchmark : Recherche avec findBy
     */
    public function testBenchmarkFindBy(): void
    {
        // Préparer les données
        for ($i = 1; $i <= self::ITERATIONS; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
        }
        $this->em->flush();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        $start = microtime(true);
        
        for ($i = 1; $i <= 10; $i++) {
            $users = $repository->findBy(['email' => "user{$i}@example.com"]);
            $this->assertCount(1, $users);
        }
        
        $end = microtime(true);
        $duration = $end - $start;
        
        // 10 recherches devraient être rapides
        $this->assertLessThan(1.0, $duration, "10 recherches findBy devraient prendre moins de 1 seconde");
    }
    
    /**
     * Benchmark : Dirty checking (mise à jour uniquement des champs modifiés)
     */
    public function testBenchmarkDirtyChecking(): void
    {
        // Créer une entité
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Original Name';
        $this->em->persist($user);
        $this->em->flush();
        
        $start = microtime(true);
        
        // Modifier seulement le nom plusieurs fois
        for ($i = 1; $i <= 50; $i++) {
            $user->name = "Updated Name {$i}";
            $this->em->flush();
        }
        
        $end = microtime(true);
        $duration = $end - $start;
        
        // Les mises à jour avec dirty checking devraient être rapides
        $this->assertLessThan(2.0, $duration, "50 mises à jour avec dirty checking devraient prendre moins de 2 secondes");
    }
    
    /**
     * Benchmark : QueryBuilder avec JOIN
     */
    public function testBenchmarkQueryBuilderJoin(): void
    {
        // Créer des données de test avec relations
        $this->createPostsTable();
        
        for ($i = 1; $i <= 10; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
            $this->em->flush();
            
            // Créer des posts pour chaque utilisateur
            for ($j = 1; $j <= 5; $j++) {
                $this->em->getConnection()->execute(
                    "INSERT INTO test_posts (user_id, title, content) VALUES (?, ?, ?)",
                    [$user->id, "Post {$j}", "Content {$j}"]
                );
            }
        }
        
        $start = microtime(true);
        
        // Utiliser une requête directe avec JOIN (QueryBuilder nécessite une classe d'entité)
        $connection = $this->em->getConnection();
        $sql = "SELECT u.*, p.* FROM test_users u INNER JOIN test_posts p ON p.user_id = u.id";
        $results = $connection->fetchAll($sql);
        
        $end = microtime(true);
        $duration = $end - $start;
        
        // Le JOIN devrait être rapide
        $this->assertLessThan(1.0, $duration, "Un JOIN avec 50 posts devrait prendre moins de 1 seconde");
        $this->assertGreaterThan(0, count($results));
    }
    
    /**
     * Benchmark : findAll avec beaucoup de données
     */
    public function testBenchmarkFindAllWithManyRecords(): void
    {
        // Créer 1000 entités
        for ($i = 1; $i <= 1000; $i++) {
            $user = new TestUser();
            $user->email = "user{$i}@example.com";
            $user->name = "User {$i}";
            $this->em->persist($user);
        }
        $this->em->flush();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        $start = microtime(true);
        $users = $repository->findAll();
        $end = microtime(true);
        
        $duration = $end - $start;
        
        $this->assertCount(1000, $users);
        // Charger 1000 entités devrait être raisonnablement rapide
        $this->assertLessThan(2.0, $duration, "Charger 1000 entités devrait prendre moins de 2 secondes");
    }
    
    private function createTestTable(): void
    {
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL
            )"
        );
    }
    
    private function createPostsTable(): void
    {
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT
            )"
        );
    }
}

#[Entity(table: 'test_users')]
class TestUser
{
    #[Id]
    #[Column(type: 'integer', autoIncrement: true)]
    public ?int $id = null;
    
    #[Column(type: 'string', length: 255)]
    public string $email;
    
    #[Column(type: 'string', length: 255)]
    public string $name;
}

