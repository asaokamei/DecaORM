<?php

namespace WScore\DecaORM\Tests\Fixtures\Relations;

use Psr\Container\ContainerInterface;

class TestContainer implements ContainerInterface
{
    private array $services = [];

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function get($id)
    {
        return $this->services[$id] ?? null;
    }

    public function has($id): bool
    {
        return isset($this->services[$id]);
    }
}
