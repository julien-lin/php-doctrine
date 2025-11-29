<?php

declare(strict_types=1);

namespace JulienLinard\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Doctrine\Tests\Fixtures\TestUser;
use JulienLinard\Doctrine\Database\SimpleQueryLogger;

class QueryLoggingTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $config = [
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ];
        
        $this->em = new EntityManager($config);
        
        // Créer la table de test
        $this->em->getConnection()->execute(
            "CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL
            )"
        );
    }

    public function testQueryLoggingEnabled(): void
    {
        $logger = $this->em->enableQueryLog(true);
        
        $this->assertTrue($logger->isEnabled());
        $this->assertCount(0, $logger->getLogs());
        
        // Exécuter une requête
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (?, ?)",
            ['test@example.com', 'Test User']
        );
        
        $logs = $logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('INSERT INTO test_users', $logs[0]['sql']);
        $this->assertEquals(['test@example.com', 'Test User'], $logs[0]['params']);
        $this->assertGreaterThan(0, $logs[0]['time']);
    }

    public function testQueryLoggingDisabled(): void
    {
        $logger = $this->em->enableQueryLog(false);
        
        // Exécuter une requête
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (?, ?)",
            ['test@example.com', 'Test User']
        );
        
        // Le logger est désactivé, donc pas de logs
        $this->assertCount(0, $logger->getLogs());
    }

    public function testMultipleQueriesLogged(): void
    {
        $logger = $this->em->enableQueryLog(true);
        
        // Exécuter plusieurs requêtes
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (?, ?)",
            ['user1@example.com', 'User 1']
        );
        
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (?, ?)",
            ['user2@example.com', 'User 2']
        );
        
        $this->em->getConnection()->execute(
            "SELECT * FROM test_users WHERE id = ?",
            [1]
        );
        
        $logs = $logger->getLogs();
        $this->assertCount(3, $logs);
        $this->assertStringContainsString('INSERT', $logs[0]['sql']);
        $this->assertStringContainsString('INSERT', $logs[1]['sql']);
        $this->assertStringContainsString('SELECT', $logs[2]['sql']);
    }

    public function testClearLogs(): void
    {
        $logger = $this->em->enableQueryLog(true);
        
        // Exécuter une requête
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (?, ?)",
            ['test@example.com', 'Test User']
        );
        
        $this->assertCount(1, $logger->getLogs());
        
        // Vider les logs
        $logger->clear();
        $this->assertCount(0, $logger->getLogs());
    }

    public function testQueryLoggingWithEntityOperations(): void
    {
        $logger = $this->em->enableQueryLog(true);
        
        // Créer et persister une entité
        $user = new TestUser();
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        $this->em->persist($user);
        $this->em->flush();
        
        // Vérifier que les requêtes sont loggées
        $logs = $logger->getLogs();
        $this->assertGreaterThan(0, count($logs));
        
        // Trouver la requête INSERT
        $insertLog = null;
        foreach ($logs as $log) {
            if (str_contains($log['sql'], 'INSERT INTO')) {
                $insertLog = $log;
                break;
            }
        }
        
        $this->assertNotNull($insertLog);
        $this->assertStringContainsString('test_users', $insertLog['sql']);
    }

    public function testGetTotalTime(): void
    {
        $logger = $this->em->enableQueryLog(true);
        
        // Exécuter plusieurs requêtes
        for ($i = 0; $i < 3; $i++) {
            $this->em->getConnection()->execute(
                "INSERT INTO test_users (email, name) VALUES (?, ?)",
                ["user{$i}@example.com", "User {$i}"]
            );
        }
        
        $totalTime = $logger->getTotalTime();
        $this->assertGreaterThan(0, $totalTime);
        
        // Vérifier que le temps total est la somme des temps individuels
        $logs = $logger->getLogs();
        $sum = array_sum(array_column($logs, 'time'));
        $this->assertEqualsWithDelta($sum, $totalTime, 0.0001);
    }

    public function testDisableQueryLog(): void
    {
        $logger = $this->em->enableQueryLog(true);
        $this->assertNotNull($this->em->getQueryLogger());
        
        $this->em->disableQueryLog();
        $this->assertNull($this->em->getQueryLogger());
    }

    public function testQueryLoggingWithNamedParameters(): void
    {
        $logger = $this->em->enableQueryLog(true);
        
        // Exécuter une requête avec paramètres nommés
        $this->em->getConnection()->execute(
            "INSERT INTO test_users (email, name) VALUES (:email, :name)",
            ['email' => 'test@example.com', 'name' => 'Test User']
        );
        
        $logs = $logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals(['email' => 'test@example.com', 'name' => 'Test User'], $logs[0]['params']);
    }
}
