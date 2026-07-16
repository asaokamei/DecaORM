<?php

namespace WScore\DecaORM\Trait;

use ReflectionClass;
use WScore\DecaORM\Attribute\Repository;
use WScore\DecaORM\Contracts\EntityInterface;
use WScore\DecaORM\OrmManager;

trait EntityTrait
{
    use EntityActionsTrait;
    use OrmContextTrait;

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
    public function getRaw(string $name): mixed
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
    public function setRaw(string $name, mixed $value): void
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * Fills the entity with data.
     *
     * - Skips id/createdAt/updatedAt properties (based on repository hydrator)
     * - Supports optional allow/deny lists via static::$fillable and static::$guarded
     * - If entity defines isFillable(string $key): bool, it is respected
     */
    public function fill(array $data): static
    {
        $repo = OrmManager::getRepository(self::getRepositoryClass());
        $hydrator = $repo->getHydrator();
        if ($hydrator === null) {
            throw new \RuntimeException('Hydrator is not available.');
        }

        $idProp = $hydrator->getPrimaryKey();
        $createdProp = $hydrator->getCreatedAt();
        $updatedProp = $hydrator->getUpdatedAt();
        $fillable = property_exists($this, 'fillable') ? $this->fillable : null;
        $guarded = property_exists($this, 'guarded') ? $this->guarded : [];

        foreach ($data as $key => $value) {
            if ($key === $idProp || $key === $createdProp || $key === $updatedProp) {
                continue;
            }
            if (is_array($fillable) && !in_array($key, $fillable, true)) {
                continue;
            }
            if (is_array($guarded) && in_array($key, $guarded, true)) {
                continue;
            }
            if (method_exists($this, 'isFillable') && !$this->isFillable($key)) {
                continue;
            }
            $setter = 'set' . ucfirst($key);
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            } else {
                $this->setRaw($key, $value);
            }
        }
        return $this;
    }

    /**
     * Converts entity properties (mapped columns) into an associative array.
     *
     * Relation values are intentionally excluded.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $repo = OrmManager::getRepository(self::getRepositoryClass());
        $hydrator = $repo->getHydrator();
        if (!$this instanceof EntityInterface) {
            throw new \RuntimeException('Entity must implement EntityInterface to use toArray().');
        }

        $data = [];
        foreach ($hydrator->listProperties() as $property) {
            $data[$property] = $this->getRaw($property);
        }
        return $data;
    }

    /**
     * Returns true if the entity's mapped columns have changed since hydration or save.
     *
     * @return bool
     */
    public function isDirty(): bool
    {
        $orm = $this->getOrm() ?? OrmManager::getInstance();
        $repo = $orm->get(self::getRepositoryClass());
        $hydrator = $repo->getHydrator();
        if (!$this instanceof EntityInterface) {
            throw new \RuntimeException('Entity must implement EntityInterface to use isDirty().');
        }

        return $orm->getDirtyTracker()->isDirty($hydrator, $this);
    }
}