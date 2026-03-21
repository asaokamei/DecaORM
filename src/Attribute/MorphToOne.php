<?php

namespace WScore\DecaORM\Attribute;

use Attribute;
use RuntimeException;
use WScore\DecaORM\Contracts\EntityInterface;

/**
 * Polymorphic BelongsToOne (one-to-one on the FK side).
 *
 * Like {@see MorphTo}, parent load results use {@see \WScore\DecaORM\Collection}, not
 * {@see \WScore\DecaORM\EntityCollection}, and parents are resolved per child entity.
 *
 * @phpstan-type TypeMap array<string, class-string<EntityInterface>>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MorphToOne
{
    /** @var string The property name on the attribute's entity */
    public string $propertyName = '';

    /**
     * @param array<string, class-string<EntityInterface>> $typeMap Stored discriminator => entity class
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
                throw new RuntimeException('MorphToOne typeMap must map each entity class at most once: ' . $class);
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
        throw new RuntimeException('MorphToOne: no discriminator for entity class ' . $entityClass);
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
