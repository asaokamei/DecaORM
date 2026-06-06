<?php

namespace WScore\DecaORM\Sql;

use WScore\DecaORM\Contracts\SqlParamMaskerInterface;

class KeyBasedSqlParamMasker implements SqlParamMaskerInterface
{
    /**
     * @param string[] $sensitiveKeys
     */
    public function __construct(
        private array $sensitiveKeys,
        private string $replacement = '***',
    ) {
        $this->sensitiveKeys = array_map('strtolower', $sensitiveKeys);
    }

    public function mask(array $params): array
    {
        return $this->maskRecursive($params);
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    private function maskRecursive(array $params): array
    {
        $masked = [];
        foreach ($params as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $this->sensitiveKeys, true)) {
                $masked[$key] = $this->replacement;
                continue;
            }
            $masked[$key] = is_array($value) ? $this->maskRecursive($value) : $value;
        }
        return $masked;
    }
}
