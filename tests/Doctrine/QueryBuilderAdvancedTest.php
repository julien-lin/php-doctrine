<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;
use JulienLinard\Doctrine\Tests\Fixtures\TestPost;
use JulienLinard\Doctrine\Tests\Fixtures\TestAdmin;

/**
 * Tests avancés pour QueryBuilder (agrégations, HAVING, sous-requêtes, UNION)
 */
class QueryBuilderAdvancedTest extends TestCase
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
        $this->createTestTables();
        $this->insertTestData();
    }
    
    /**
     * Test des fonctions d'agrégation COUNT
     */
    public function testCountAggregation(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestPost::class, 'p')
           ->count('p.id', 'total');
        
        $result = $qb->getOneOrNullResult();
        
        $this->assertNotNull($result);
        $this->assertEquals(5, (int)$result['total']);
    }
    
    /**
     * Test des fonctions d'agrégation SUM
     */
    public function testSumAggregation(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestPost::class, 'p')
           ->sum('p.views', 'total_views');
        
        $result = $qb->getOneOrNullResult();
        
        $this->assertNotNull($result);
        $this->assertEquals(150, (int)$result['total_views']);
    }
    
    /**
     * Test des fonctions d'agrégation AVG
     */
    public function testAvgAggregation(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestPost::class, 'p')
           ->avg('p.views', 'avg_views');
        
        $result = $qb->getOneOrNullResult();
        
        $this->assertNotNull($result);
        $this->assertEquals(30.0, (float)$result['avg_views']);
    }
    
    /**
     * Test de GROUP BY avec agrégations
     */
    public function testGroupByWithAggregations(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestPost::class, 'p')
           ->select('p.category_id')
           ->count('p.id', 'total')
           ->groupBy('p.category_id');
        
        $results = $qb->getResult();
        
        $this->assertCount(2, $results);
    }
    
    /**
     * Test de HAVING
     */
    public function testHaving(): void
    {
        // Test simple : vérifier que HAVING fonctionne
        // Sans HAVING, on devrait avoir 2 catégories (1 et 2)
        $qb1 = $this->em->createQueryBuilder();
        $qb1->from(TestPost::class, 'p')
            ->select('p.category_id')
            ->count('p.id', 'total')
            ->groupBy('p.category_id');
        
        $resultsWithoutHaving = $qb1->getResult();
        $this->assertEquals(2, count($resultsWithoutHaving)); // 2 catégories
        
        // Avec HAVING COUNT >= 2, on devrait toujours avoir 2 catégories
        $qb2 = $this->em->createQueryBuilder();
        $qb2->from(TestPost::class, 'p')
            ->select('p.category_id')
            ->count('p.id', 'total')
            ->groupBy('p.category_id')
            ->having('COUNT(p.id) >= ?', 2);
        
        // Debug: afficher la requête et les paramètres
        // echo "SQL: " . $qb2->getSql() . PHP_EOL;
        // echo "Params: ";
        // print_r($qb2->getParameters());
        // echo PHP_EOL;
        
        $results = $qb2->getResult();
        
        // Les deux catégories ont >= 2 posts, donc les deux devraient être retournées
        $this->assertEquals(2, count($results));
    }
    
    /**
     * Test de sous-requête avec IN
     */
    public function testSubqueryIn(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUser::class, 'u')
           ->whereSubquery('u.id', 'IN', function($subQb) {
               $subQb->from(TestPost::class, 'p')
                     ->select('p.user_id')
                     ->where('p.views > ?', 20);
           });
        
        $results = $qb->getResult();
        
        $this->assertGreaterThanOrEqual(1, count($results));
    }
    
    /**
     * Test de EXISTS
     */
    public function testExists(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUser::class, 'u')
           ->whereExists(function($subQb) {
               $subQb->from(TestPost::class, 'p')
                     ->where('p.user_id = u.id')
                     ->where('p.views > ?', 20);
           });
        
        $results = $qb->getResult();
        
        $this->assertGreaterThanOrEqual(1, count($results));
    }
    
    /**
     * Test de NOT EXISTS
     */
    public function testNotExists(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->from(TestUser::class, 'u')
           ->whereNotExists(function($subQb) {
               $subQb->from(TestPost::class, 'p')
                     ->where('p.user_id = u.id');
           });
        
        $results = $qb->getResult();
        
        // Devrait retourner les utilisateurs sans posts
        $this->assertIsArray($results);
    }
    
    /**
     * Test de UNION
     */
    public function testUnion(): void
    {
        // SQLite nécessite que les deux SELECT aient le même nombre de colonnes
        // et les mêmes types, donc on utilise CAST pour s'assurer de la compatibilité
        $qb1 = $this->em->createQueryBuilder();
        $qb1->from(TestUser::class, 'u')
            ->select('CAST(u.id AS TEXT) as id', 'u.name');
        
        $qb2 = $this->em->createQueryBuilder();
        $qb2->from(TestAdmin::class, 'a')
            ->select('CAST(a.id AS TEXT) as id', 'a.name');
        
        $qb1->union($qb2);
        
        $results = $qb1->getResult();
        
        $this->assertGreaterThanOrEqual(2, count($results));
    }
    
    private function createTestTables(): void
    {
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL
            )"
        );
        
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                views INTEGER NOT NULL DEFAULT 0
            )"
        );
        
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL
            )"
        );
    }
    
    private function insertTestData(): void
    {
        // Créer des utilisateurs
        for ($i = 1; $i <= 3; $i++) {
            $this->em->getConnection()->execute(
                "INSERT INTO test_users (name) VALUES (?)",
                ["User {$i}"]
            );
        }
        
        // Créer des posts
        // Catégorie 1 : 3 posts, Catégorie 2 : 2 posts
        $posts = [
            ['user_id' => 1, 'category_id' => 1, 'title' => 'Post 1', 'views' => 10],
            ['user_id' => 1, 'category_id' => 1, 'title' => 'Post 2', 'views' => 20],
            ['user_id' => 2, 'category_id' => 1, 'title' => 'Post 3', 'views' => 30],
            ['user_id' => 2, 'category_id' => 2, 'title' => 'Post 4', 'views' => 40],
            ['user_id' => 3, 'category_id' => 2, 'title' => 'Post 5', 'views' => 50],
        ];
        
        foreach ($posts as $post) {
            $this->em->getConnection()->execute(
                "INSERT INTO test_posts (user_id, category_id, title, views) VALUES (?, ?, ?, ?)",
                [$post['user_id'], $post['category_id'], $post['title'], $post['views']]
            );
        }
        
        // Créer un admin
        $this->em->getConnection()->execute(
            "INSERT INTO test_admins (name) VALUES (?)",
            ["Admin 1"]
        );
    }
}

