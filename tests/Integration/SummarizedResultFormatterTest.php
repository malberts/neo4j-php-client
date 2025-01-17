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

use function bin2hex;
use function dump;

use Exception;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

use function random_bytes;
use function serialize;
use function unserialize;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<SummarizedResult<CypherMap<OGMTypes>>>
 */
final class SummarizedResultFormatterTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        return SummarizedResultFormatter::create();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testAcceptanceRead(string $alias): void
    {
        $result = $this->getClient()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN 1 AS one'), $alias);
        self::assertInstanceOf(SummarizedResult::class, $result);
        self::assertEquals(1, $result->first()->get('one'));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws Exception
     */
    public function testAcceptanceWrite(string $alias): void
    {
        $counters = $this->getClient()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('CREATE (x:X {y: $x}) RETURN x', ['x' => bin2hex(random_bytes(128))]), $alias)->getSummary()->getCounters();
        self::assertEquals(new SummaryCounters(1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, true), $counters);
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws Exception
     */
    public function testGetResults(string $alias): void
    {
        $results = $this->getClient()->run('RETURN 1 AS one', [], $alias)->getResults();

        self::assertNotInstanceOf(SummarizedResult::class, $results);
        self::assertInstanceOf(CypherList::class, $results);

        $jsonSerialize = $results->jsonSerialize();
        self::assertIsArray($jsonSerialize);
        self::assertArrayNotHasKey('summary', $jsonSerialize);
        self::assertArrayNotHasKey('result', $jsonSerialize);

        $first = $results->first();
        self::assertInstanceOf(CypherMap::class, $first);
        self::assertEquals(1, $first->get('one'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testSerialize(string $alias): void
    {
        $results = $this->getClient()->run('RETURN 1 AS one', [], $alias);

        $serialise = serialize($results);
        $resultHasBeenSerialized = unserialize($serialise);

        self::assertInstanceOf(SummarizedResult::class, $resultHasBeenSerialized);
        self::assertEquals($results->toRecursiveArray(), $resultHasBeenSerialized->toRecursiveArray());
    }

    /**
     * @dataProvider connectionAliases
     *
     * @doesNotPerformAssertions
     */
    public function testDump(string $alias): void
    {
        $results = $this->getClient()->run('RETURN 1 AS one', [], $alias);

        dump($results);
    }

    public function testConsumedPositive(): void
    {
        $results = $this->getClient()->run('RETURN 1 AS one');

        self::assertInstanceOf(SummarizedResult::class, $results);

        self::assertGreaterThan(0, $results->getSummary()->getResultConsumedAfter());
    }

    public function testAvailableAfter(): void
    {
        $results = $this->getClient()->run('RETURN 1 AS one');

        self::assertInstanceOf(SummarizedResult::class, $results);

        self::assertGreaterThan(0, $results->getSummary()->getResultAvailableAfter());
    }
}
