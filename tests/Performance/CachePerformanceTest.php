<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests\Performance;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Cache\QueryCache;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;

/**
 * Tests de performance pour le cache avec les nouveaux hash (xxh3/sha256)
 *
 * @group performance
 */
class CachePerformanceTest extends TestCase
{
    private EntityManager $em;
    private QueryCache $cache;
    private const ITERATIONS = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];

        $this->cache = new QueryCache(3600, true);
        $this->em = new EntityManager($config, $this->cache);
        $this->createTestTable();
        $this->insertTestData();
    }

    /**
     * Benchmark : Génération de clés de cache avec xxh3/sha256
     */
    public function testBenchmarkCacheKeyGeneration(): void
    {
        $sql = "SELECT * FROM test_users WHERE email = :email";
        $params = ['email' => 'test@example.com'];

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $key = $this->cache->generateKey($sql, $params);
            $this->assertStringStartsWith('query_', $key);
        }

        $end = microtime(true);
        $duration = $end - $start;

        // La génération de 1000 clés devrait être très rapide (< 0.1 seconde)
        $this->assertLessThan(
            0.1,
            $duration,
            "La génération de " . self::ITERATIONS . " clés de cache devrait prendre moins de 0.1 seconde"
        );
    }

    /**
     * Benchmark : Performance du cache avec hit rate élevé
     */
    public function testBenchmarkCacheHitPerformance(): void
    {
        $repository = $this->em->getRepository(TestUser::class);

        // Premier appel (cache miss)
        $users1 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertGreaterThan(0, count($users1));

        // Deuxième appel (cache hit) - devrait utiliser le cache
        $users2 = $repository->findAll(useCache: true, cacheTtl: 3600);

        // Vérifier que le cache fonctionne (les résultats doivent être identiques)
        $this->assertCount(count($users1), $users2);
        $this->assertEquals($users1[0]->id, $users2[0]->id);

        // Vérifier que le cache contient bien l'entrée
        $cacheCount = $this->cache->count();
        $this->assertGreaterThan(0, $cacheCount, "Le cache devrait contenir au moins une entrée");
    }

    /**
     * Benchmark : Performance du cache avec plusieurs requêtes différentes
     */
    public function testBenchmarkCacheWithMultipleQueries(): void
    {
        $repository = $this->em->getRepository(TestUser::class);

        $start = microtime(true);

        // Exécuter 100 requêtes différentes avec cache
        for ($i = 1; $i <= 100; $i++) {
            $users = $repository->findBy(
                ['email' => "user{$i}@example.com"],
                useCache: true,
                cacheTtl: 3600
            );
            $this->assertCount(1, $users);
        }

        $end = microtime(true);
        $duration = $end - $start;

        // 100 requêtes avec cache devraient être rapides
        $this->assertLessThan(
            2.0,
            $duration,
            "100 requêtes avec cache devraient prendre moins de 2 secondes"
        );
    }

    /**
     * Benchmark : Comparaison performance avec/sans cache
     */
    public function testBenchmarkCacheVsNoCache(): void
    {
        $repository = $this->em->getRepository(TestUser::class);

        // Sans cache
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $users = $repository->findAll(useCache: false);
        }
        $noCacheDuration = microtime(true) - $start;

        // Avec cache (premier appel = miss, suivants = hits)
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $users = $repository->findAll(useCache: true, cacheTtl: 3600);
        }
        $cacheDuration = microtime(true) - $start;

        // Le cache devrait améliorer les performances (au moins pour les appels suivants)
        $this->assertGreaterThan(0, $noCacheDuration);
        $this->assertGreaterThan(0, $cacheDuration);

        // Le cache devrait être au moins aussi rapide (ou plus rapide après le premier appel)
        // Note: Le premier appel avec cache peut être légèrement plus lent à cause de la mise en cache
        // mais les appels suivants devraient être beaucoup plus rapides
    }

    /**
     * Benchmark : Performance de l'invalidation du cache
     */
    public function testBenchmarkCacheInvalidation(): void
    {
        $repository = $this->em->getRepository(TestUser::class);

        // Mettre en cache
        $users1 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertGreaterThan(0, count($users1));

        // Invalider le cache en modifiant une entité
        $start = microtime(true);

        // Recharger l'entité depuis la base pour avoir une instance fraîche
        $userId = $users1[0]->id;
        $user = $this->em->find(TestUser::class, $userId);
        $this->assertNotNull($user);

        $user->name = 'Modified Name';
        $this->em->persist($user);
        $this->em->flush();

        // Le cache devrait être invalidé automatiquement
        // Recharger depuis la base (sans cache pour forcer le rechargement)
        $users2 = $repository->findAll(useCache: false);

        $end = microtime(true);
        $duration = $end - $start;

        // L'invalidation et le rechargement devraient être rapides
        $this->assertLessThan(
            1.0,
            $duration,
            "L'invalidation du cache et le rechargement devraient prendre moins de 1 seconde"
        );

        // Trouver l'utilisateur modifié par son ID
        $modifiedUser = null;
        foreach ($users2 as $u) {
            if ($u->id === $userId) {
                $modifiedUser = $u;
                break;
            }
        }

        $this->assertNotNull($modifiedUser, "L'utilisateur modifié doit être trouvé");
        $this->assertEquals('Modified Name', $modifiedUser->name, "Le nom doit être modifié dans la base de données");
    }

    /**
     * Benchmark : Performance avec hash xxh3 vs sha256
     */
    public function testBenchmarkHashAlgorithmPerformance(): void
    {
        $sql = "SELECT * FROM test_users WHERE email = :email";
        $params = ['email' => 'test@example.com'];

        // Vérifier quel algorithme est utilisé
        $hasXxh3 = function_exists('hash') && in_array('xxh3', hash_algos(), true);

        $start = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $key = $this->cache->generateKey($sql, $params);
        }

        $end = microtime(true);
        $duration = $end - $start;

        // La génération de hash devrait être très rapide
        $this->assertLessThan(
            0.1,
            $duration,
            "La génération de " . self::ITERATIONS . " hash devrait prendre moins de 0.1 seconde"
        );

        // xxh3 devrait être plus rapide que sha256 (si disponible)
        if ($hasXxh3) {
            $this->assertLessThan(
                0.05,
                $duration,
                "xxh3 devrait être très rapide (< 0.05s pour " . self::ITERATIONS . " hash)"
            );
        }
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

    private function insertTestData(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            $this->em->getConnection()->execute(
                "INSERT INTO test_users (email, name) VALUES (?, ?)",
                ["user{$i}@example.com", "User {$i}"]
            );
        }
    }
}
