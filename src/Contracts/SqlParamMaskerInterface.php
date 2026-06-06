<?php

namespace WScore\DecaORM\Contracts;

interface SqlParamMaskerInterface
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function mask(array $params): array;
}
