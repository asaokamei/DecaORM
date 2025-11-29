<?php

namespace WScore\DecaORM;

trait EntityTrait
{
    /**
     * プロパティの値を取得（文字列として返す）
     * DBからの読み書き専用。型変換が必要な場合はgetterメソッドを使用すること。
     */
    public function get(string $name): ?string
    {
        if (property_exists($this, $name)) {
            $value = $this->$name;
            return $value !== null ? (string) $value : null;
        }
        return null;
    }

    /**
     * プロパティの値を設定（文字列として設定）
     * DBからの読み書き専用。型変換が必要な場合はsetterメソッドを使用すること。
     */
    public function set(string $name, mixed $value): void
    {
        if (property_exists($this, $name)) {
            $this->$name = $value !== null ? (string) $value : null;
        }
    }
}