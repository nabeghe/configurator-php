<?php namespace Nabeghe\Configurator\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Configurator and Proxy
 */
#[CoversClass(\Nabeghe\Configurator\Configurator::class)]
class ConfiguratorTest extends TestCase
{
    private string $testConfigPath;

    private Config $config;

    protected function setUp(): void
    {
        $this->testConfigPath = __DIR__.'/config';
        $this->config = new Config($this->testConfigPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testConfigPath.'/_.cache')) {
            unlink($this->testConfigPath.'/_.cache');
        }
    }

    public function testDbConfigReadByPropertyAccess(): void
    {
        $db = $this->config->db;

        $this->assertSame('mysql', $db->type);
        $this->assertSame('sqlite', $db->getDefault('type'));
        $this->assertSame('localhost', $db->host);
        $this->assertSame(3306, $db->port);
        $this->assertSame('utf8mb4', $db->charset);
        $this->assertSame('utf8mb4_unicode_ci', $db->collation);
        $this->assertSame('nabeghe_configurator_', $db->prefix);
        $this->assertSame(200, $db->maxConnections);
        $this->assertSame(30, $db->connectionTimeout);
        $this->assertNull($db->nothing ?? null);
    }

    public function testDbConfigReadByDotNotation(): void
    {
        $config = $this->config;

        $this->assertSame('mysql', $config->dot('db.type'));
        $this->assertSame('localhost', $config->dot('db.host'));
        $this->assertSame(3306, $config->dot('db.port'));
        $this->assertSame('utf8mb4', $config->dot('db.charset'));
        $this->assertSame('utf8mb4_unicode_ci',$config->dot('db.collation'));
        $this->assertSame('nabeghe_configurator_', $config->dot('db.prefix'));
        $this->assertSame(200, $config->dot('db.max_connections'));
        $this->assertSame(30, $config->dot('db.connection_timeout'));
        $this->assertNull($config->dot('nothing.prefix'));
    }

    public function testDbConfigReadByArrayIndex(): void
    {
        $db = $this->config->db;

        $this->assertSame('mysql', $db['type']);
        $this->assertSame('localhost', $db['host']);
        $this->assertSame(3306, $db['port']);
        $this->assertSame('utf8mb4', $db['charset']);
        $this->assertSame('utf8mb4_unicode_ci', $db['collation']);
        $this->assertSame('nabeghe_configurator_', $db['prefix']);
        $this->assertSame(200, $db['max_connections']);
        $this->assertSame(30, $db['connectionTimeout']);
        $this->assertNull($db['nothing']);
    }

    public function testDbConfigEditAndEject(): void
    {
        $db = $this->config->db;

        $mainValue = $db->prefix;

        $db->prefix = 'test_';
        $this->assertSame('test_', $db->prefix);

        $this->assertTrue($db->save());

        $db->eject();

        $db = $this->config->db;
        $this->assertSame('test_', $db->prefix);

        $this->config->dot('db.prefix', $mainValue);
        $this->assertTrue($db->save());
    }
}
