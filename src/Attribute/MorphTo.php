<?php

namespace WScore\DecaORM\Attribute;

use Attribute;
use RuntimeException;
use WScore\DecaORM\Contracts\EntityInterface;

/**
 * Polymorphic BelongsTo (many-to-one): the child holds foreign id + type discriminator.
 *
 * Loading this relation returns a generic {@see \WScore\DecaORM\Collection} of parents (not
 * {@see \WScore\DecaORM\EntityCollection}), because parents may be different entity classes;
 * resolution is per child row.
 *
 * @phpstan-type TypeMap array<string, class-string<EntityInterface>>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphTo
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    /**
     * @param string $foreignKey   FK property holding the parent id (e.g. commentable_id)
     * @param string $typeColumn   Property holding the discriminator (e.g. commentable_type)
     * @param array<string, class-string<EntityInterface>> $typeMap Stored discriminator => entity class
     * @param string|null $inversedBy Inverse property on the parent (e.g. HasMany comments)
     */
    public function __construct(
        public string $foreignKey,
        public string $typeColumn,
        public array $typeMap,
        public ?string $inversedBy = null,
    ) {
        $seen = [];
        foreach ($this->typeMap as $disc => $class) {
            if (isset($seen[$class])) {
                throw new RuntimeException('MorphTo typeMap must map each entity class at most once: ' . $class);
            }
            $seen[$class] = true;
        }
    }

    /**
     * @param class-string<EntityInterface> $entityClass
     */
    public function discriminatorForClass(string $entityClass): string
    {
        foreach ($this->typeMap as $disc => $class) {
            if ($class === $entityClass) {
                return $disc;
            }
        }
        throw new RuntimeException('MorphTo: no discriminator for entity class ' . $entityClass);
    }

    public function associate(EntityInterface $entity, ?EntityInterface $target): void
    {
        $entity->setRaw($this->propertyName, $target);
        if ($target === null) {
            $entity->setRaw($this->foreignKey, null);
            $entity->setRaw($this->typeColumn, null);
            return;
        }
        $disc = $this->discriminatorForClass($target::class);
        $id = $target->getId();
        $entity->setRaw($this->foreignKey, $id !== null ? (string) $id : null);
        $entity->setRaw($this->typeColumn, $disc);
    }
}
