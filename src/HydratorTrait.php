<?php

namespace WScore\DecaORM;

trait HydratorTrait
{
    public abstract function getPrimaryKey(): string;
    abstract protected function listProperties(): array;

    /**
     * Converts an associative array (DB row) to an entity (hydration)
     * @return EntityInterface
     */
    protected function hydrateEntity(EntityInterface $entity, array $data): mixed
    {
        $targetEntity = EntityCache::cache($entity);

        // Set data to the target entity (update with latest data even if cached)
        foreach ($this->listProperties() as $property) {
            if (isset($data[$property])) {
                // set Column value as string; this is for compatibility with the EntityTrait
                $targetEntity->set($property, (string) $data[$property]);
            }
        }

        return $targetEntity;
    }

    /**
     * Converts an entity to an associative array (dehydration)
     */
    public function dehydrateEntity(EntityInterface $entity): array
    {
        $data = [];
        foreach ($this->listProperties() as $property) {
            $data[$property] = $entity->get($property);
        }
        return $data;
    }
}