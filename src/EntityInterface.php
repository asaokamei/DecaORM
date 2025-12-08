<?php

namespace WScore\DecaORM;

interface EntityInterface
{
    /**
     * returns a class name for the repository class for the entity.
     */
    public static function getRepositoryClass(): string;
    public function getId(): null|int|string;
    public function get(string $name): mixed;
    public function set(string $name, mixed $value): void;
}