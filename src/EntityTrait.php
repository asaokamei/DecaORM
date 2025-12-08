<?php

namespace WScore\DecaORM;

use ReflectionClass;
use WScore\DecaORM\Attribute\Repository;

trait EntityTrait
{
    private static string $repositoryClass;

    /**
     * @override this method when creating own hydrator
     * @return string
     */
    public static function getRepositoryClass(): string
    {
        if (isset(self::$repositoryClass)) {
            return self::$repositoryClass;
        }
        $reflection = new ReflectionClass(self::class);
        $attributes = $reflection->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof Repository) {
                self::$repositoryClass = $instance->class;
                return self::$repositoryClass;
            }
        }
        throw new \RuntimeException('Repository class is not defined.');
    }

    /**
     * Get the value of a property (return as string)
     * Read-only from DB. If type conversion is needed, use the getter method.
     */
    public function get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            $value = $this->$name;
            return $value;
        }
        return null;
    }

    /**
     * Set the value of a property (set as string)
     * Read-only from DB. If type conversion is needed, use the setter method.
     */
    public function set(string $name, mixed $value): void
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }
}