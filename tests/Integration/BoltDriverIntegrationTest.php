<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use Bolt\error\ConnectException;
use Dotenv\Dotenv;
use Exception;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Databags\SummarizedResult;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

final class BoltDriverIntegrationTest extends TestCase
{
    private ?UriInterface $uri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uri = $this->getBoltUri();
    }

    private function getBoltUri(): ?UriInterface
    {
        /** @var string|mixed $connections */
        $connections = $_ENV['CONNECTIONS'] ?? false;
        if (!is_string($connections)) {
            Dotenv::createImmutable(__DIR__.'/../../')->load();
            /** @var string|mixed $connections */
            $connections = $_ENV['CONNECTIONS'] ?? false;
            if (!is_string($connections)) {
                $connections = 'bolt://neo4j:test@neo4j,neo4j://neo4j:test@core1,http://neo4j:test@neo4j';
            }
        }
        foreach (explode(',', $connections) as $uri) {
            $psrUri = Uri::create($uri);
            if ($psrUri->getScheme() === 'bolt') {
                return $psrUri;
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function testValidHostname(): void
    {
        if ($this->uri === null) {
            self::markTestSkipped('No bolt uri provided');
        }

        $results = BoltDriver::create($this->uri->__toString())->createSession()->run(<<<'CYPHER'
RETURN 1 AS x
CYPHER);
        self::assertEquals(1, $results->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testValidUrl(): void
    {
        if ($this->uri === null) {
            self::markTestSkipped('No bolt uri provided');
        }

        $ip = gethostbyname($this->uri->getHost());
        $results = BoltDriver::create($this->uri->withHost($ip)->__toString())->createSession()->run(<<<'CYPHER'
RETURN 1 AS x
CYPHER);
        self::assertEquals(1, $results->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testInvalidIp(): void
    {
        $driver = BoltDriver::create('bolt://neo4j:test@999.999.999.999');
        $this->expectException(ConnectException::class);
        $driver->createSession()->run('RETURN 1');
    }

    /**
     * @throws Exception
     */
    public function testInvalidSocket(): void
    {
        $driver = BoltDriver::create('bolt://neo4j:test@999.999.999.999');
        $this->expectException(ConnectException::class);
        $driver->createSession()->run('RETURN 1');
    }

    public function testBookmarkUpdates(): void
    {
        if ($this->uri === null) {
            self::markTestSkipped('No bolt uri provided');
        }

        $session = BoltDriver::create($this->uri->__toString())->createSession();
        $bookmark = $session->getLastBookmark();
        $this->assertEquals([], $bookmark->values());
        $this->assertTrue($bookmark->isEmpty());
        $previousBookmark = $bookmark;

        /** @var SummarizedResult $result */
        $result = $session->run('MATCH (x) RETURN x');
        $result->preload();

        $bookmark = $session->getLastBookmark();
        $this->assertFalse($bookmark->isEmpty());
        $this->assertNotEquals($previousBookmark->values(), $bookmark->values());
        $previousBookmark = $bookmark;

        /** @var SummarizedResult $result */
        $result = $session->run('CREATE (x:Node)');
        $result->preload();

        $bookmark = $session->getLastBookmark();
        $this->assertFalse($bookmark->isEmpty());
        $this->assertNotEquals($previousBookmark->values(), $bookmark->values());
    }
}
