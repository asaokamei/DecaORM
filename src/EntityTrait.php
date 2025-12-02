<?php

namespace WScore\DecaORM;

trait EntityTrait
{
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