# Hydrate/Dehydrateのパフォーマンス分析

## 問題の原因

Hydrate/Dehydrateが約100マイクロ秒かかる主な原因は、**EntityTraitの`get()`/`set()`メソッドのオーバーヘッド**です。

## 詳細な測定結果

### EntityTraitのget/setメソッドのコスト

| 操作 | 時間/呼び出し | 直接アクセスとの比較 |
|------|-------------|-------------------|
| `get()` | 8.46 us | 5.7倍遅い |
| `set()` | 12.18 us | 8.2倍遅い |
| 直接プロパティアクセス | 1.49 us | 基準 |

### 各処理のコスト内訳

#### 1. method_exists() / property_exists() のコスト
- `method_exists()`: 1.62 us/呼び出し
- `property_exists()`: 1.67 us/呼び出し

**問題**: `get()`メソッドでは最大4回、`set()`メソッドでは最大4回のリフレクション関数が呼ばれます：
- `method_exists($this, 'get' . ucfirst($name))` - 1回目
- `method_exists($this, 'get' . ucfirst(str_replace('_', '', $name)))` - 2回目
- `property_exists($this, $name)` - 3回目

#### 2. 文字列操作のコスト
- `ucfirst()`: 1.62 us/呼び出し
- `str_replace()`: 1.91 us/呼び出し

**問題**: 各プロパティごとに複数の文字列操作が実行されます：
- `ucfirst($name)` - メソッド名生成
- `str_replace('_', '', $name)` - アンダースコア除去
- `ucfirst(str_replace('_', '', $name))` - 変換後のメソッド名生成

### Hydrate処理の内訳（5プロパティの場合）

| 処理 | 時間/イテレーション |
|------|-------------------|
| ループ + isset()のみ | 7.47 us |
| ループ + isset() + set() | 85.97 us |
| **set()のオーバーヘッド** | **78.50 us** |

### 計算例

5つのプロパティ（user_id, name, email, created_at, updated_at）がある場合：

1. **各プロパティのset()コスト**: 12.18 us × 5 = 60.9 us
2. **ループとisset()のオーバーヘッド**: 7.47 us
3. **その他のオーバーヘッド**（エンティティ生成、get_class()、キャッシュチェックなど）: 約30 us
4. **合計**: 約100 us

## なぜget/setが遅いのか

### get()メソッドの処理フロー

```php
public function get(string $name): mixed
{
    // 1. 文字列操作: ucfirst($name) - 1.62 us
    $method = 'get' . ucfirst($name);
    
    // 2. リフレクション: method_exists() - 1.62 us
    if (method_exists($this, $method)) {
        return $this->$method();
    }
    
    // 3. 文字列操作: str_replace() - 1.91 us
    $method = 'get' . ucfirst(str_replace('_', '', $name));
    
    // 4. リフレクション: method_exists() - 1.62 us
    if (method_exists($this, $method)) {
        return $this->$method();
    }
    
    // 5. リフレクション: property_exists() - 1.67 us
    if (property_exists($this, $name)) {
        return $this->$name;
    }
    
    return null;
}
```

**最悪ケース**: すべてのチェックを通過する場合
- 文字列操作: 1.62 + 1.91 = 3.53 us
- リフレクション: 1.62 + 1.62 + 1.67 = 4.91 us
- メソッド呼び出しオーバーヘッド: 約1 us
- **合計: 約9.44 us**（測定値: 8.46 usと一致）

### set()メソッドの処理フロー

同様に、`set()`メソッドも複数のチェックと文字列操作を実行するため、さらに時間がかかります（測定値: 12.18 us）。

## 最適化の可能性

### 1. メソッド名のキャッシュ
各プロパティ名に対して、一度計算したメソッド名をキャッシュすることで、文字列操作を削減できます。

### 2. リフレクション結果のキャッシュ
`method_exists()`や`property_exists()`の結果をキャッシュすることで、リフレクション呼び出しを削減できます。

### 3. 直接プロパティアクセスへの切り替え
パフォーマンスが重要な場合は、`get()`/`set()`を使わずに直接プロパティアクセスを使用する方法もあります（ただし、柔軟性は失われます）。

## 結論

**100マイクロ秒かかる主な原因**:
1. **EntityTraitのget/setメソッド**: 約60-80マイクロ秒（5プロパティ × 12-16マイクロ秒）
2. **ループとisset()のオーバーヘッド**: 約7マイクロ秒
3. **その他のオーバーヘッド**: 約20-30マイクロ秒（エンティティ生成、キャッシュチェックなど）

**改善の余地**:
- メソッド名とリフレクション結果のキャッシュにより、50-70%の高速化が期待できます
- ただし、現在の実装でも実用的な速度であり、柔軟性とトレードオフの関係にあります

