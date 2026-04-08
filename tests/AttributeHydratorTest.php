<?php

namespace WScore\DecaORM\Tests;

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\Tests\Fixtures\Relations\User;

class AttributeHydratorTest extends TestCase
{
    public function testAttributeHydrator(): void
    {
        $hydrator = new AttributeHydrator(User::class);

        $this->assertEquals(User::class, $hydrator->getEntityClass());
        $this->assertEquals('users', $hydrator->getTableName());
        $this->assertEquals('id', $hydrator->getPrimaryKey());
        $this->assertTrue($hydrator->isPkAutoNumber());
        $this->assertEquals('registered_at', $hydrator->getCreatedAt());
        $this->assertEquals('updated_at', $hydrator->getUpdatedAt());

        $properties = $hydrator->listProperties();
        $this->assertContains('id', $properties);
        $this->assertContains('name', $properties);
        $this->assertContains('email', $properties);
        $this->assertContains('registered_at', $properties);
        $this->assertContains('updated_at', $properties);
        $this->assertNotContains('posts', $properties);

        $this->assertEquals('created_at', $hydrator->getCreatedAtColumn());
        $this->assertEquals('updated_at', $hydrator->getUpdatedAtColumn());
    }

    public function testHydrate(): void
    {
        $hydrator = new AttributeHydrator(User::class);

        $data = [
            'user_id' => 1,
            'user_name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $entity = $hydrator->hydrate($data);

        $this->assertInstanceOf(User::class, $entity);
        $this->assertEquals(1, $entity->getId());
        $this->assertEquals('Test User', $entity->getRaw('name'));
        $this->assertEquals('test@example.com', $entity->getRaw('email'));
    }

    public function testHydrateAlwaysMaterializesNewInstances(): void
    {
        $hydrator = new AttributeHydrator(User::class);
        $data = [
            'user_id' => 1,
            'user_name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $first = $hydrator->hydrate($data);
        $second = $hydrator->hydrate($data);
        $this->assertNotSame($first, $second);
        $this->assertEquals('Test User', $second->getRaw('name'));
        $this->assertEquals($first->getRaw('name'), $second->getRaw('name'));
    }

    public function testDehydrate(): void
    {
        $hydrator = new AttributeHydrator(User::class);

        $entity = new User();
        $entity->setRaw('id', 1);
        $entity->setRaw('name', 'Test User');
        $entity->setRaw('email', 'test@example.com');

        $data = $hydrator->dehydrate($entity);

        $this->assertEquals(1, $data['user_id']);
        $this->assertEquals('Test User', $data['user_name']);
        $this->assertEquals('test@example.com', $data['email']);
    }
}


