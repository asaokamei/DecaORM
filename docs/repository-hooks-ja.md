# リポジトリフック（Repository hooks）

テナント境界・論理削除・楽観ロックなど、**横断的な永続化ルール**を、リポジトリの継承の組み合わせ爆発なしに差し込むための拡張点です。

## 契約

- **`WScore\DecaORM\Contracts\RepositoryHooksInterface`** — フックのメソッド一覧を定義します。
- 具象リポジトリでは **`protected ?RepositoryHooksInterface $hooks = null`** に実装を代入します（未設定時は **`NoOpHooks`** が使われます）。

## ライフサイクル（命名）

| タイミング | メソッド | 用途のイメージ |
|------------|----------|----------------|
| SELECT の `Query` が組み立てられたあと、実行前 | `beforeQuery(Query $query)` | テナント条件・論理削除の「有効行のみ」など |
| INSERT 用の列データが用意されたあと、実行前 | `beforeInsert(EntityInterface $entity, array &$data)` | 既定値の補完など |
| INSERT 実行後 | `afterInsert(EntityInterface $entity)` | 必要なら後処理 |
| `Update` が組み立てられたあと（PK の WHERE 付き）、実行前 | `beforeUpdate(Update $update, EntityInterface $entity, array $data, ?array $snapshot)` | 楽観ロックの `WHERE` 追加など |
| UPDATE 実行後、DirtyTracker のスナップショット更新前 | `afterUpdate(EntityInterface $entity, ?PDOStatement $updateStatement = null)` | メモリ上のフィールド整合、`rowCount()` 参照 |
| `Delete` が組み立てられたあと、実行前 | `beforeDelete(Delete $delete, EntityInterface $entity)` | 物理削除の抑止など |
| DELETE 実行後 | `afterDelete(EntityInterface $entity)` | 後処理 |

**`before*`** = その SQL を **実行する直前**、**`after*`** = **実行後**、という対称で揃えています。

### `beforeInsert` だけ引数が違う理由

- **SELECT / UPDATE / DELETE** は **`Query` / `Update` / `Delete` ビルダー** に句を足していくモデルです。
- **INSERT** は、Hydrator の結果を **`$data`（列名 => 値）** にまとめてから `INSERT` へ載せる流れが中心で、フックの典型用途も **列の追加・上書き** です。そのため **`array &$data` の参照渡し** が素直です。
- 見た目の対称性のため `Insert` ビルダーを渡す案もありえますが、**配列で十分なことが多い**ため、現状はこの形にしています。

### `afterUpdate` の第2引数 `?PDOStatement`

UPDATE の `execute()` が返した文を渡します（取得できない場合は `null`）。**`PDOStatement::rowCount()`** で影響行数を見られます（楽観ロックで 0 行を検知する用途など）。フック実装側で行数が不要なら無視して構いません。

### `applyHooksToQuery(Query $query)`

`sqlQuery()` および `Query::newQuery()` から自動で呼ばれます。**`new Query($repository)` だけ**で `Query` を作った場合は、必要に応じて **`$repository->applyHooksToQuery($query)`** を自分で呼んでください。

## 複数フックの順序

**`WScore\DecaORM\Persistence\CompositeHooks`** に `RepositoryHooksInterface` の配列を渡すと、**配列順**に各メソッドが呼ばれます。テナント条件と論理削除を両方使う場合など、**意図した順**で登録してください（AND 条件なので結果は同じでも、可読性のため順序を固定するのがおすすめです）。

## サンプル実装（`src/Persistence/`）

| クラス | 役割 |
|--------|------|
| **`NoOpHooks`** | 何もしないデフォルト。サブクラスで必要なメソッドだけ上書き可能。 |
| **`CompositeHooks`** | 複数フックを合成。 |
| **`TenantScopeHooks`** | `beforeQuery` でスコープ列（例: `tenant_id`）を絞り込み。 |
| **`SoftDeleteHooks`** | `beforeQuery` で `deleted_at IS NULL` など。物理削除を拒否するオプションあり。 |
| **`VersionColumnHooks`** | 楽観ロック（`WHERE version = ?` と `version = version + 1`）。下記参照。 |

## `VersionColumnHooks` の前提と挙動

1. **version 列は通常どおり `#[Column]` でマップする**  
   DirtyTracker のスナップショットに version が載らないと、`beforeUpdate` で期待バージョンを取れません。

2. **UPDATE の SET 用差分 `$data` に version 列を含めない**  
   バージョンの増分は SQL 側（`version = version + 1`）に任せます。差分に version が入ると **意図的に例外**にします（二重更新の防止）。

3. **`afterUpdate` でエンティティの version をメモリ上で進める**  
   DB では `UPDATE` で既に +1 済みですが、PHP のエンティティは自動では追従しません。その直後に **`DirtyTracker::takeEntity()`** が走るため、**メモリ上の version を DB と一致させないと**、次の保存で楽観ロックの前提が崩れます。

4. **0 行更新と `OptimisticLockException`**  
   競合などで `WHERE version = ?` に合致しなければ影響行数 0 になり得ます。既定では **`rowCount() === 0` のときに例外**を投げます（無効化するコンストラクタオプションあり）。

## プリセットで足りないとき

フックやサンプルで足りない場合は、従来どおり **`insertEntity` / `updateEntity` / `sqlInsert` などをリポジトリでオーバーライド**する逃げ道は有効です。

## 関連コード

- `WScore\DecaORM\Trait\RepositoryTrait` — フックの呼び出し箇所
- `WScore\DecaORM\Contracts\RepositoryHooksInterface`
- `WScore\DecaORM\Contracts\OptimisticLockException`
