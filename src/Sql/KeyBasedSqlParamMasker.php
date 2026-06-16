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
            if (is_string($key)) {
                $normalizedKey = strtolower($key);
                $isSensitive = false;
                foreach ($this->sensitiveKeys as $sensitiveKey) {
                    if ($normalizedKey === $sensitiveKey) {
                        $isSensitive = true;
                        break;
                    }
                    // プレースホルダ形式への対応: col_0, set_col_1, _EXPAND_col_2 など
                    // 末尾のカウンタ (_[0-9]+) を除去
                    $baseKey = preg_replace('/_(\d+)$/', '', $normalizedKey);
                    if ($baseKey === $sensitiveKey) {
                        $isSensitive = true;
                        break;
                    }
                    // prefix除去: set_, _EXPAND_
                    $baseKey = preg_replace('/^(set_|_expand_)/', '', $baseKey);
                    if ($baseKey === $sensitiveKey) {
                        $isSensitive = true;
                        break;
                    }
                }

                if ($isSensitive) {
                    $masked[$key] = $this->replacement;
                    continue;
                }
            }
            $masked[$key] = is_array($value) ? $this->maskRecursive($value) : $value;
        }
        return $masked;
    }
}
