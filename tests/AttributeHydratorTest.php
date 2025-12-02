<?php

namespace WScore\DecaORM\Tests;

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\AttributeHydrator;
use WScore\DecaORM\Tests\Users\User;

class AttributeHydratorTest extends TestCase
{
    public function testAttributeHydrator(): void
    {
        $hydrator = new AttributeHydrator(User::class);

        $this->assertEquals(User::class, $hydrator->getEntityClass());
        $this->assertEquals('users', $hydrator->getTableName());
        $this->assertEquals('user_id', $hydrator->getPrimaryKey());
        $this->assertTrue($hydrator->isPkAutoNumber());
        $this->assertEquals('created_at', $hydrator->getCreatedAt());
        $this->assertEquals('updated_at', $hydrator->getUpdatedAt());

        $properties = $hydrator->listProperties();
        $this->assertContains('user_id', $properties);
        $this->assertContains('name', $properties);
        $this->assertContains('email', $properties);
        $this->assertContains('created_at', $properties);
        $this->assertContains('updated_at', $properties);
        $this->assertNotContains('posts', $properties);
    }

    public function testHydrate(): void
    {
        $hydrator = new AttributeHydrator(User::class);

        $data = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $entity = $hydrator->hydrate($data);

        $this->assertInstanceOf(User::class, $entity);
        $this->assertEquals(1, $entity->getId());
        $this->assertEquals('Test User', $entity->get('name'));
        $this->assertEquals('test@example.com', $entity->get('email'));
    }

    public function testDehydrate(): void
    {
        $hydrator = new AttributeHydrator(User::class);

        $entity = new User();
        $entity->setId(1);
        $entity->set('name', 'Test User');
        $entity->set('email', 'test@example.com');

        $data = $hydrator->dehydrate($entity);

        $this->assertEquals(1, $data['user_id']);
        $this->assertEquals('Test User', $data['name']);
        $this->assertEquals('test@example.com', $data['email']);
    }
}


