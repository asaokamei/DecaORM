<?php

namespace WScore\DecaORM\Tests\Users;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private array $services = [];

    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }

    public function get(string $id)
    {
        return $this->services[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}