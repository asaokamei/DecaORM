<?php

namespace WScore\DecaORM\Persistence;

use WScore\DecaORM\Sql\Query;

/**
 * Sample: adds {@see Query::where()} on a scope column (tenant id, org id, store id, …) in {@see beforeQuery()}.
 *
 * Instantiate per repository (or per request) with the current scope value from your app context.
 * Combine with {@see CompositeHooks} together with {@see SoftDeleteHooks}
 * and {@see VersionColumnHooks} in a fixed order if you use several.
 */
class TenantScopeHooks extends NoOpHooks
{
    /**
     * @param string $scopeColumn SQL column name (e.g. {@code tenant_id})
     * @param mixed  $scopeValue  Bound value (e.g. current tenant id)
     */
    public function __construct(
        private string $scopeColumn,
        private mixed $scopeValue,
    ) {
    }

    public function beforeQuery(Query $query): void
    {
        $query->where($this->scopeColumn, $this->scopeValue);
    }
}
