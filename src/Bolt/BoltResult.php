<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use BadMethodCallException;
use Generator;
use Iterator;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use function array_splice;
use function call_user_func;
use function count;

/**
 * @psalm-import-type BoltCypherStats from \Laudis\Neo4j\Contracts\FormatterInterface
 *
 * @implements Iterator<int, list>
 */
final class BoltResult implements Iterator
{
    private BoltConnection $connection;
    private int $fetchSize;
    /** @var list<list> */
    private array $rows = [];
    private ?array $meta = null;
    /** @var (callable(array):void)|null */
    private $finishedCallback;
    private int $qid;

    public function __construct(BoltConnection $connection, int $fetchSize, int $qid)
    {
        $this->connection = $connection;
        $this->fetchSize = $fetchSize;
        $this->qid = $qid;
        $this->connection->incrementOwner();
        if ($this->connection->getProtocol() === ConnectionProtocol::BOLT_V3()) {
            $this->fetchResults();
        }
    }

    public function getFetchSize(): int
    {
        return $this->fetchSize;
    }

    private ?Generator $it = null;

    /**
     * @param callable(array):void $finishedCallback
     */
    public function setFinishedCallback(callable $finishedCallback): void
    {
        $this->finishedCallback = $finishedCallback;
    }

    /**
     * @return Generator<int, list>
     */
    public function getIt(): Generator
    {
        if ($this->it === null) {
            $this->it = $this->iterator();
        }

        return $this->it;
    }

    /**
     * @return Generator<int, list>
     */
    public function iterator(): Generator
    {
        if ($this->connection->getProtocol() === ConnectionProtocol::BOLT_V3()) {
            yield from $this->rows;
        } else {
            $i = 0;
            while ($this->meta === null) {
                $this->fetchResults();
                foreach ($this->rows as $row) {
                    yield $i => $row;
                    ++$i;
                }
            }
        }

        if ($this->finishedCallback) {
            call_user_func($this->finishedCallback, $this->meta ?? []);
        }
    }

    public function consume(): array
    {
        while ($this->valid()) {
            $this->next();
        }

        return $this->meta ?? [];
    }

    private function fetchResults(): void
    {
        $meta = $this->connection->pull($this->qid, $this->fetchSize);

        /** @var list<list> $rows */
        $rows = array_splice($meta, 0, count($meta) - 1);
        $this->rows = $rows;

        /** @var array{0: array} $meta */
        if (!array_key_exists('has_more', $meta[0]) || $meta[0]['has_more'] === false) {
            $this->meta = $meta[0];
            $this->connection->decrementOwner();
            $this->connection->close();
        }
    }

    /**
     * @return list
     */
    public function current(): array
    {
        return $this->getIt()->current();
    }

    public function next(): void
    {
        $this->getIt()->next();
    }

    public function key(): int
    {
        return $this->getIt()->key();
    }

    public function valid(): bool
    {
        return $this->getIt()->valid();
    }

    public function rewind(): void
    {
        // Rewind is impossible
        throw new BadMethodCallException('Cannot rewind a bolt result.');
    }

    public function __destruct()
    {
        if ($this->connection->isOpen()) {
            $this->discard();
        }
        if ($this->meta === null) {
            $this->connection->decrementOwner();
            $this->connection->close();
        }
    }

    public function discard(): void
    {
        $this->connection->discard($this->qid);
    }
}
