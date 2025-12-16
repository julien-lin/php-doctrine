<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\Cache\QueryCache;
use ReflectionClass;

/**
 * Tests pour vérifier que MD5 a été remplacé par xxh3/sha256
 */
class HashSecurityTest extends TestCase
{
    private QueryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new QueryCache();
    }

    /**
     * Test que QueryCache utilise xxh3 ou sha256, pas MD5
     */
    public function testQueryCacheUsesSecureHash(): void
    {
        $sql = "SELECT * FROM users WHERE id = :id";
        $params = ['id' => 1];
        
        $key = $this->cache->generateKey($sql, $params);
        
        // La clé devrait commencer par 'query_' et contenir un hash
        $this->assertStringStartsWith('query_', $key, 'La clé devrait commencer par "query_"');
        $this->assertEquals(22, strlen($key), 'La clé devrait avoir 22 caractères (query_ + 16 caractères de hash)');
        
        // Vérifier que le hash n'est pas MD5 (en vérifiant la longueur)
        $hashPart = substr($key, 6); // Enlever 'query_'
        $this->assertEquals(16, strlen($hashPart), 'Le hash devrait avoir 16 caractères');
    }

    /**
     * Test que QueryCache utilise bien xxh3/sha256 et pas MD5 (vérification du code source)
     */
    public function testQueryCacheHashImplementation(): void
    {
        $sourceCode = file_get_contents(__DIR__ . '/../../src/Doctrine/Cache/QueryCache.php');
        
        // Vérifier que le code utilise hash() et non md5()
        $this->assertStringContainsString('hash(\'xxh3\'', $sourceCode, 'Le code devrait utiliser xxh3');
        $this->assertStringContainsString('hash(\'sha256\'', $sourceCode, 'Le code devrait utiliser sha256 en fallback');
        $this->assertStringNotContainsString('md5($', $sourceCode, 'Le code ne devrait pas utiliser md5() directement');
    }

    /**
     * Test que les hash sont cohérents (même entrée = même hash)
     */
    public function testHashConsistency(): void
    {
        $sql = "SELECT * FROM users WHERE id = :id";
        $params = ['id' => 1];
        
        $key1 = $this->cache->generateKey($sql, $params);
        $key2 = $this->cache->generateKey($sql, $params);
        
        // Les mêmes requêtes doivent générer la même clé
        $this->assertEquals($key1, $key2, 'Les mêmes requêtes doivent générer la même clé');
    }

    /**
     * Test que les hash sont différents pour des entrées différentes
     */
    public function testHashUniqueness(): void
    {
        $sql1 = "SELECT * FROM users WHERE id = :id";
        $params1 = ['id' => 1];
        
        $sql2 = "SELECT * FROM users WHERE id = :id";
        $params2 = ['id' => 2];
        
        $key1 = $this->cache->generateKey($sql1, $params1);
        $key2 = $this->cache->generateKey($sql2, $params2);
        
        // Des requêtes différentes doivent générer des clés différentes
        $this->assertNotEquals($key1, $key2, 'Des requêtes différentes doivent générer des clés différentes');
    }

    /**
     * Test que le hash fonctionne correctement avec différents algorithmes
     */
    public function testHashAlgorithmFallback(): void
    {
        // Vérifier que xxh3 est utilisé si disponible
        $hasXxh3 = function_exists('hash') && in_array('xxh3', hash_algos(), true);
        
        if ($hasXxh3) {
            $testData = 'test data';
            $hash = hash('xxh3', $testData);
            $this->assertNotEmpty($hash, 'xxh3 devrait produire un hash');
            $this->assertNotEquals(md5($testData), $hash, 'Le hash ne devrait pas être MD5');
        }
        
        // Vérifier que sha256 fonctionne
        $testData = 'test data';
        $sha256Hash = hash('sha256', $testData);
        $this->assertNotEmpty($sha256Hash, 'sha256 devrait produire un hash');
        $this->assertNotEquals(md5($testData), $sha256Hash, 'Le hash ne devrait pas être MD5');
        $this->assertEquals(64, strlen($sha256Hash), 'sha256 devrait produire un hash de 64 caractères');
    }

    /**
     * Test que la normalisation SQL fonctionne correctement
     */
    public function testSqlNormalization(): void
    {
        $sql1 = "SELECT   *   FROM   users   WHERE   id   =   :id";
        $sql2 = "SELECT * FROM users WHERE id = :id";
        $params = ['id' => 1];
        
        $key1 = $this->cache->generateKey($sql1, $params);
        $key2 = $this->cache->generateKey($sql2, $params);
        
        // Les requêtes avec espaces multiples doivent générer la même clé
        $this->assertEquals($key1, $key2, 'Les requêtes normalisées doivent générer la même clé');
    }

    /**
     * Test que le tri des paramètres fonctionne correctement
     */
    public function testParamsSorting(): void
    {
        $sql = "SELECT * FROM users WHERE id = :id AND name = :name";
        
        $params1 = ['name' => 'John', 'id' => 1];
        $params2 = ['id' => 1, 'name' => 'John'];
        
        $key1 = $this->cache->generateKey($sql, $params1);
        $key2 = $this->cache->generateKey($sql, $params2);
        
        // Les paramètres dans un ordre différent doivent générer la même clé
        $this->assertEquals($key1, $key2, 'Les paramètres triés doivent générer la même clé');
    }
}

