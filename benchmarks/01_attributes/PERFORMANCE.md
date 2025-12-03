# パフォーマンス測定結果

## Opcache有効/無効での比較

### 測定環境
- PHP 8.5.0
- イテレーション数: 10,000回
- Opcache: Zend OPcache v8.5.0

### 結果サマリー

| 項目 | Opcache有効 | Opcache無効 | 改善率 |
|------|------------|------------|--------|
| **AttributeHydrator インスタンス化** | 12.29 us | 15.42 us | **約20%高速化** |
| **Manual Hydrator インスタンス化** | 2.28 us | 2.21 us | ほぼ同じ |
| **AttributeHydrator Hydrate** | 106.57 us | 106.82 us | ほぼ同じ |
| **Manual Hydrator Hydrate** | 105.00 us | 105.20 us | ほぼ同じ |
| **AttributeHydrator Dehydrate** | 64.88 us | 64.76 us | ほぼ同じ |
| **Manual Hydrator Dehydrate** | 64.24 us | 64.60 us | ほぼ同じ |

### 詳細結果

#### Opcache有効時

```
=== Performance Benchmark ===
Iterations: 10,000
Opcache enabled: Yes
Opcache memory used: 9.13 MB
Opcache cached scripts: 8

--- Hydrator Creation ---
  AttributeHydrator creation: 122.94 ms total, 12.29 us per instance
  Manual Hydrator creation: 22.77 ms total, 2.28 us per instance
  Overhead: 10.02 us (absolute), 5.4x (relative)

--- Hydrate Operation ---
  AttributeHydrator hydrate: 1065.71 ms total, 106.57 us per call
  Manual Hydrator hydrate: 1049.96 ms total, 105.00 us per call
  Overhead: 1.58 us (absolute), 1.015x (relative)

--- Dehydrate Operation ---
  AttributeHydrator dehydrate: 648.80 ms total, 64.88 us per call
  Manual Hydrator dehydrate: 642.35 ms total, 64.24 us per call
  Overhead: 0.64 us (absolute), 1.010x (relative)
```

#### Opcache無効時

```
=== Performance Benchmark ===
Iterations: 10,000
Opcache enabled: No

--- Hydrator Creation ---
  AttributeHydrator creation: 154.17 ms total, 15.42 us per instance
  Manual Hydrator creation: 22.10 ms total, 2.21 us per instance
  Overhead: 13.21 us (absolute), 7.0x (relative)

--- Hydrate Operation ---
  AttributeHydrator hydrate: 1068.24 ms total, 106.82 us per call
  Manual Hydrator hydrate: 1052.00 ms total, 105.20 us per call
  Overhead: 1.62 us (absolute), 1.015x (relative)

--- Dehydrate Operation ---
  AttributeHydrator dehydrate: 647.61 ms total, 64.76 us per call
  Manual Hydrator dehydrate: 645.95 ms total, 64.60 us per call
  Overhead: 0.17 us (absolute), 1.003x (relative)
```

## 考察

### Opcacheの影響

1. **インスタンス化時の改善**
   - AttributeHydratorのインスタンス化が約20%高速化（15.42 us → 12.29 us）
   - リフレクション関連のクラス（ReflectionClass、ReflectionPropertyなど）がOpcacheによって最適化されるため
   - ただし、実際のアプリケーションではHydratorは一度作成して再利用されるため、実用的な影響は限定的

2. **実行時の影響**
   - Hydrate/Dehydrate操作では、Opcacheの有無による差はほとんどない（1-2マイクロ秒程度）
   - これらの操作は主にデータのコピーやプロパティアクセスに依存しており、Opcacheの影響を受けにくい

### 結論

- **Opcache有効時**: AttributeHydratorのインスタンス化が約20%高速化される
- **実行時のオーバーヘッド**: Opcacheの有無に関わらず、数マイクロ秒（0.6-1.6マイクロ秒）程度
- **実用的な影響**: 通常、Hydratorは一度作成して再利用されるため、実用上の差はほとんどない

## 実行方法

```bash
# Opcache有効で実行
php tests/PerformanceBenchmark.php

# Opcache無効で実行
php -d opcache.enable_cli=0 tests/PerformanceBenchmark.php
```

