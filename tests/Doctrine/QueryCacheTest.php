<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Cache\QueryCache;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;

/**
 * Tests pour le Query Cache
 */
class QueryCacheTest extends TestCase
{
    private EntityManager $em;
    private QueryCache $cache;
    
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
    }
    
    public function testCacheEnabled(): void
    {
        $this->assertTrue($this->cache->isEnabled());
        
        $this->cache->setEnabled(false);
        $this->assertFalse($this->cache->isEnabled());
    }
    
    public function testCacheSetAndGet(): void
    {
        $key = 'test_key';
        $value = ['data' => 'test'];
        
        $this->cache->set($key, $value, 60);
        $cached = $this->cache->get($key);
        
        $this->assertEquals($value, $cached);
    }
    
    public function testCacheExpiration(): void
    {
        $key = 'test_key';
        $value = ['data' => 'test'];
        
        // Mettre en cache avec un TTL très court
        $this->cache->set($key, $value, 1);
        
        // Vérifier que la valeur est en cache
        $this->assertNotNull($this->cache->get($key));
        
        // Attendre que le cache expire
        sleep(2);
        
        // Vérifier que la valeur a expiré
        $this->assertNull($this->cache->get($key));
    }
    
    public function testCacheKeyGeneration(): void
    {
        $sql1 = "SELECT * FROM users WHERE id = :id";
        $params1 = ['id' => 1];
        
        $sql2 = "SELECT * FROM users WHERE id = :id";
        $params2 = ['id' => 1];
        
        $key1 = $this->cache->generateKey($sql1, $params1);
        $key2 = $this->cache->generateKey($sql2, $params2);
        
        // Les mêmes requêtes doivent générer la même clé
        $this->assertEquals($key1, $key2);
        
        // Des requêtes différentes doivent générer des clés différentes
        $sql3 = "SELECT * FROM users WHERE id = :id";
        $params3 = ['id' => 2];
        $key3 = $this->cache->generateKey($sql3, $params3);
        
        $this->assertNotEquals($key1, $key3);
    }
    
    public function testRepositoryFindAllWithCache(): void
    {
        // Insérer des données de test
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Premier appel : pas de cache
        $users1 = $repository->findAll(useCache: true, cacheTtl: 60);
        $this->assertCount(3, $users1);
        
        // Deuxième appel : doit utiliser le cache
        $users2 = $repository->findAll(useCache: true, cacheTtl: 60);
        $this->assertCount(3, $users2);
        
        // Vérifier que les entités sont identiques
        $this->assertEquals($users1[0]->id, $users2[0]->id);
    }
    
    public function testRepositoryFindByWithCache(): void
    {
        // Insérer des données de test
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Premier appel : pas de cache
        $users1 = $repository->findBy(['name' => 'User 1'], useCache: true, cacheTtl: 60);
        $this->assertCount(1, $users1);
        
        // Deuxième appel : doit utiliser le cache
        $users2 = $repository->findBy(['name' => 'User 1'], useCache: true, cacheTtl: 60);
        $this->assertCount(1, $users2);
        
        // Vérifier que les entités sont identiques
        $this->assertEquals($users1[0]->id, $users2[0]->id);
    }
    
    public function testCacheInvalidationOnInsert(): void
    {
        // Insérer des données de test
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Mettre en cache
        $users1 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(3, $users1);
        
        // Insérer une nouvelle entité
        $newUser = new TestUser();
        $newUser->email = 'new@example.com';
        $newUser->name = 'New User';
        $this->em->persist($newUser);
        $this->em->flush();
        
        // Le cache doit être invalidé, donc on doit récupérer 4 utilisateurs
        $users2 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(4, $users2);
    }
    
    public function testCacheInvalidationOnUpdate(): void
    {
        // Insérer des données de test
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Mettre en cache findAll
        $users1 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(3, $users1);
        
        // Récupérer l'ID de la première entité
        $userId = $users1[0]->id;
        
        // Recharger l'entité depuis la base (pour avoir une instance fraîche)
        $user = $this->em->find(TestUser::class, $userId);
        $this->assertNotNull($user);
        
        // Modifier l'entité
        $user->name = 'Modified User';
        $this->em->persist($user);
        $this->em->flush();
        
        // Le cache doit être invalidé, donc findAll doit retourner les données mises à jour depuis la base
        $users2 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(3, $users2);
        
        // Trouver l'utilisateur modifié par son ID
        $modifiedUser = null;
        foreach ($users2 as $u) {
            if ($u->id === $userId) {
                $modifiedUser = $u;
                break;
            }
        }
        
        $this->assertNotNull($modifiedUser, "L'utilisateur modifié doit être trouvé");
        $this->assertEquals('Modified User', $modifiedUser->name, "Le nom doit être modifié dans la base de données");
    }
    
    public function testCacheInvalidationOnDelete(): void
    {
        // Insérer des données de test
        $this->insertTestData();
        
        $repository = $this->em->getRepository(TestUser::class);
        
        // Mettre en cache
        $users1 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(3, $users1);
        
        // Supprimer une entité
        $user = $users1[0];
        $this->em->remove($user);
        $this->em->flush();
        
        // Le cache doit être invalidé
        $users2 = $repository->findAll(useCache: true, cacheTtl: 3600);
        $this->assertCount(2, $users2);
    }
    
    public function testCacheClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $this->assertEquals(2, $this->cache->count());
        
        $this->cache->clear();
        
        $this->assertEquals(0, $this->cache->count());
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }
    
    private function createTestTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS test_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL
        )";
        
        $this->em->getConnection()->execute($sql);
    }
    
    private function insertTestData(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->em->getConnection()->execute(
                "INSERT INTO test_users (email, name) VALUES (?, ?)",
                ["user{$i}@example.com", "User {$i}"]
            );
        }
    }
}
