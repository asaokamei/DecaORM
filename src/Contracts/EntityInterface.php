<?php

namespace WScore\DecaORM\Contracts;

interface EntityInterface
{
    /**
     * returns a class name for the repository class for the entity.
     */
    public static function getRepositoryClass(): string;

    /**
     * Retrieves the unique identifier for the entity.
     */
    public function getId(): null|int|string;

    /**
     * Retrieves the property value of the name.
     */
    public function getRaw(string $name): mixed;

    /**
     * Sets the property value of the name.
     */
    public function setRaw(string $name, mixed $value): void;
}