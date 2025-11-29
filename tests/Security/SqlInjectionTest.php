<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests\Security;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;

/**
 * Tests de sécurité contre les injections SQL
 */
class SqlInjectionTest extends TestCase
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
     * Test de protection contre l'injection SQL dans findBy
     */
    public function testSqlInjectionInFindBy(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Tentative d'injection SQL via le critère
        $maliciousInput = "'; DROP TABLE test_users; --";
        
        // Doit lancer une exception ou retourner un résultat vide
        // mais ne doit PAS exécuter le DROP TABLE
        try {
            $users = $repository->findBy(['email' => $maliciousInput]);
            // Si aucune exception n'est levée, vérifier que la table existe toujours
            $this->assertTableExists('test_users');
            $this->assertIsArray($users);
        } catch (\Exception $e) {
            // Exception attendue pour les identifiants invalides
            $this->assertTableExists('test_users');
        }
    }
    
    /**
     * Test de protection contre l'injection SQL dans QueryBuilder
     */
    public function testSqlInjectionInQueryBuilder(): void
    {
        $qb = $this->em->createQueryBuilder();
        
        // Tentative d'injection SQL via la condition WHERE
        $maliciousInput = "'; DROP TABLE test_users; --";
        
        try {
            $qb->from(TestUser::class, 'u')
               ->where('email = ?', $maliciousInput);
            
            $results = $qb->getResult();
            
            // La table doit toujours exister
            $this->assertTableExists('test_users');
            $this->assertIsArray($results);
        } catch (\Exception $e) {
            // Exception attendue
            $this->assertTableExists('test_users');
        }
    }
    
    /**
     * Test de protection contre l'injection SQL dans les alias
     */
    public function testSqlInjectionInAlias(): void
    {
        $qb = $this->em->createQueryBuilder();
        
        // Tentative d'injection SQL via l'alias
        $maliciousAlias = "u; DROP TABLE test_users; --";
        
        $this->expectException(\InvalidArgumentException::class);
        
        $qb->from(TestUser::class, $maliciousAlias);
    }
    
    /**
     * Test de protection contre l'injection SQL dans les noms de colonnes
     */
    public function testSqlInjectionInColumnNames(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Tentative d'injection SQL via le nom de colonne dans orderBy
        $maliciousColumn = "email; DROP TABLE test_users; --";
        
        $this->expectException(\InvalidArgumentException::class);
        
        $repository->findBy([], [$maliciousColumn => 'ASC']);
    }
    
    /**
     * Test que les requêtes préparées protègent contre l'injection
     */
    public function testPreparedStatementsProtection(): void
    {
        $connection = $this->em->getConnection();
        
        // Tentative d'injection SQL classique
        $maliciousInput = "'; DROP TABLE test_users; --";
        
        // Utiliser une requête préparée
        $sql = "SELECT * FROM test_users WHERE email = :email";
        $result = $connection->fetchOne($sql, ['email' => $maliciousInput]);
        
        // La table doit toujours exister
        $this->assertTableExists('test_users');
        
        // Le résultat doit être null (pas d'utilisateur avec cet email)
        $this->assertNull($result);
    }
    
    /**
     * Test de protection contre l'injection SQL dans les valeurs numériques
     */
    public function testSqlInjectionInNumericValues(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Tentative d'injection SQL via une valeur numérique
        $maliciousId = "1 OR 1=1";
        
        try {
            $user = $repository->find((int)$maliciousId);
            // Si la conversion échoue, $user sera null
            // Si elle réussit, on cherche l'ID 1 (normal)
            $this->assertTableExists('test_users');
        } catch (\Exception $e) {
            $this->assertTableExists('test_users');
        }
    }
    
    /**
     * Test de protection contre l'injection SQL dans les LIMIT/OFFSET
     */
    public function testSqlInjectionInLimitOffset(): void
    {
        $repository = $this->em->getRepository(TestUser::class);
        
        // Les valeurs LIMIT/OFFSET sont castées en int, donc protégées
        // Mais testons quand même
        $maliciousLimit = "1; DROP TABLE test_users; --";
        
        $users = $repository->findBy([], null, (int)$maliciousLimit);
        
        // La table doit toujours exister
        $this->assertTableExists('test_users');
        $this->assertIsArray($users);
    }
    
    private function assertTableExists(string $tableName): void
    {
        $connection = $this->em->getConnection();
        $pdo = $connection->getPdo();
        
        // Vérifier que la table existe toujours
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
        $result = $pdo->prepare($sql);
        $result->execute([$tableName]);
        $exists = $result->fetch() !== false;
        
        $this->assertTrue($exists, "La table {$tableName} devrait exister");
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
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        $this->em->persist($user);
        $this->em->flush();
    }
}

