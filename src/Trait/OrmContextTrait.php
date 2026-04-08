<?php

namespace WScore\DecaORM\Trait;

use WScore\DecaORM\OrmManager;

/**
 * Optional ORM context on entities (set by repositories after hydrate / createEntity).
 */
trait OrmContextTrait
{
    private ?OrmManager $orm = null;

    public function setOrm(?OrmManager $orm): void
    {
        $this->orm = $orm;
    }

    public function getOrm(): ?OrmManager
    {
        return $this->orm;
    }

    /**
     * Omit {@see OrmManager} (and thus PDO) from default serialization of entities.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = [];
        foreach ((new \ReflectionObject($this))->getProperties() as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            if ($prop->getName() === 'orm') {
                continue;
            }
            $data[$prop->getName()] = $prop->getValue($this);
        }
        return $data;
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $name => $value) {
            $this->{$name} = $value;
        }
        $this->orm = null;
    }
}
