<?php

namespace WScore\DecaORM\Tests\Sql;

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Sql\KeyBasedSqlParamMasker;

class KeyBasedSqlParamMaskerTest extends TestCase
{
    public function testMasksExactMatch(): void
    {
        $masker = new KeyBasedSqlParamMasker(['password']);
        $params = ['password' => 'secret', 'id' => 1];
        $expected = ['password' => '***', 'id' => 1];
        $this->assertSame($expected, $masker->mask($params));
    }

    public function testMasksWithCounters(): void
    {
        $masker = new KeyBasedSqlParamMasker(['password']);
        $params = [
            'password_0' => 'secret1',
            'set_password_1' => 'secret2',
            'password' => 'secret3',
            'other_field' => 'safe'
        ];
        
        $masked = $masker->mask($params);
        
        $this->assertSame('***', $masked['password_0'], 'password_0 should be masked');
        $this->assertSame('***', $masked['set_password_1'], 'set_password_1 should be masked');
        $this->assertSame('***', $masked['password'], 'password should be masked');
        $this->assertSame('safe', $masked['other_field']);
    }

    public function testMasksCaseInsensitive(): void
    {
        $masker = new KeyBasedSqlParamMasker(['Password']);
        $params = ['password_0' => 'secret'];
        $masked = $masker->mask($params);
        $this->assertSame('***', $masked['password_0']);
    }

    public function testMasksWithExpandPrefix(): void
    {
        $masker = new KeyBasedSqlParamMasker(['id']);
        $params = [
            '_EXPAND_id_0' => 10,
            '_EXPAND_id_1' => 20,
        ];
        $masked = $masker->mask($params);
        $this->assertSame('***', $masked['_EXPAND_id_0']);
        $this->assertSame('***', $masked['_EXPAND_id_1']);
    }
}
