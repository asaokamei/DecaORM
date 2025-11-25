<?php

namespace WScore\DecaORM;

interface EntityInterface
{
    public function getId(): null|int|string;
    public function get(string $name): mixed;
    public function set(string $name, mixed $value): void;
}