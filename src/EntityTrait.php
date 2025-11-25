<?php

namespace WScore\DecaORM;

use DateTimeImmutable;

trait EntityTrait
{

    public function get(string $name): mixed
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        $method = 'get' . ucfirst(str_replace('_', '', $name));
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }

    public function set(string $name, mixed $value): void
    {
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) {
            $this->$method($value);
            return;
        }
        $method = 'set' . ucfirst(str_replace('_', '', $name));
        if (method_exists($this, $method)) {
            $this->$method($value);
            return;
        }
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    private function setDateTimeProperty(string $name, mixed $value): void
    {
        if (is_string($value)) {
            $value = new DateTimeImmutable($value);
        }
        $this->$name = $value;
    }
}