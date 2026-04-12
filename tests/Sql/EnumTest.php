<?php

namespace WScore\DecaORM\Tests\Sql;

use PHPUnit\Framework\TestCase;
use WScore\DecaORM\Sql\QueryBuilder;

require_once __DIR__ . '/EnumDef.php';

class EnumTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new QueryBuilder();
    }

    public function testWhereWithEnum()
    {
        $this->builder->from('users')
            ->where('status', Status::ACTIVE);
        
        $params = $this->builder->getParameters();
        $this->assertEquals('active', $params['status_0']);
    }

    public function testWhereInWithEnum()
    {
        $this->builder->from('users')
            ->whereIn('status', [Status::ACTIVE, Status::INACTIVE]);
        
        $params = $this->builder->getParameters();
        // whereIn generates placeholders like _EXPAND_status_0_0, _EXPAND_status_0_1
        $this->assertEquals('active', $params['_EXPAND_status_0_0']);
        $this->assertEquals('inactive', $params['_EXPAND_status_0_1']);
    }

    public function testWhereWithIntEnum()
    {
        $this->builder->from('users')
            ->where('level', IntStatus::ONE);
        
        $params = $this->builder->getParameters();
        $this->assertEquals(1, $params['level_0']);
    }

    public function testSetParametersWithEnum()
    {
        $this->builder->from('users')
            ->whereRaw('status = :status', ['status' => Status::INACTIVE]);
        
        $params = $this->builder->getParameters();
        $this->assertEquals('inactive', $params['status']);
    }

    public function testSetParametersWithEnumArray()
    {
        // whereIn-like manual parameters
        $this->builder->from('users')
            ->whereRaw('status IN (:_EXPAND_ids)', ['ids' => [Status::ACTIVE, Status::INACTIVE]]);
        
        $params = $this->builder->getParameters();
        $this->assertEquals('active', $params['_EXPAND_ids_0']);
        $this->assertEquals('inactive', $params['_EXPAND_ids_1']);
    }
}
