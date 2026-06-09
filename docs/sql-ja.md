# SQL ビルダー マニュアル

DecaORMのSQLビルダーを使用して、型安全で柔軟なSQLクエリを構築できます。

## 目次

- [Query（SELECT文）](#queryselect文)
- [Raw SELECT / FROM](#raw-select-and-from)
- [DISTINCT, GROUP BY, HAVING, FOR UPDATE](#distinct-group-by-having-for-update)
- [追記句のリセット（`clear*`）](#追記句のリセットclear)
- [Insert（INSERT文）](#insertinsert文)
- [Update（UPDATE文）](#updateupdate文)
- [Delete（DELETE文）](#deletedelete文)
- [IN句の配列展開](#in句の配列展開)
- [注意事項](#注意事項)

---

## Query（SELECT文）

Repositoryから`sqlQuery()`メソッドでQueryオブジェクトを取得します。

### 基本的な使い方

```php
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->getResult();
```

### メソッド一覧

- `select(string ...$columns)` - SELECT 列リストを**置き換え**（追記ではない。`clearSelect()` はなく、再指定は `select()`）
- `addSelect(string ...$columns)` - SELECT 列を**追記**（識別子をエスケープ）
- `selectRaw(string $expression, array $bindings = [])` - 生の SELECT 式を既存列の**後ろに追記**
- `from(string $table)` - FROM句を指定（通常は自動設定）
- `fromRaw(string $fragment, array $bindings = [])` - FROM を生断片で指定（派生テーブルなど）
- `where(string $column, mixed $value, string $operator = '=')` - WHERE条件を追加
- `whereIn(string $column, array $values)` - WHERE IN条件を追加
- `whereNotIn(string $column, array $values)` - WHERE NOT IN条件を追加
- `whereRaw(string $sql_snippet, array $bindings = [])` - 生のWHERE句を追加
- `joinRaw(string $raw_join_sql)` - JOIN句を追加
- `withRaw(string $cte_sql)` - WITH句（CTE）を追加
- `distinct(bool $on = true)` - `SELECT DISTINCT`（既定は false 相当＝付けない）
- `groupBy(string ...$columns)` - GROUP BY（複数回呼ぶと列が順に追加される）
- `having(string $column, mixed $value, string $operator = '=')` - HAVING 条件（AND で連結）
- `havingRaw(string $sql_snippet, array $bindings = [])` - 生の HAVING 断片
- `orderBy(string $column, string $direction = 'ASC')` - ORDER BY句を安全に指定
- `orderByRaw(string $sqlSnippet)` - 生の ORDER BY 断片を追加
- `clearWhere()` - WHERE 条件をすべてクリア（[追記句のリセット](#追記句のリセットclear) 参照）
- `clearJoin()` - JOIN 句をすべてクリア
- `clearGroupBy()` - GROUP BY 列をすべてクリア
- `clearHaving()` - HAVING 条件をすべてクリア
- `clearOrderBy()` - ORDER BY 式をすべてクリア
- `clearParameters()` - バインドパラメータ袋を空にする
- `limit(?int $limit)` - LIMIT句を指定
- `offset(?int $offset)` - OFFSET句を指定
- `forUpdate(bool $on = true, bool $noWait = false, bool $skipLocked = false)` - 末尾に `FOR UPDATE`（LIMIT/OFFSET の後）。`$noWait` で `NOWAIT`、`$skipLocked` で `SKIP LOCKED`
- `getResult()` - クエリを実行してEntityCollectionを取得
- `executeCountQuery()` - COUNT(*) を実行し件数（int）を返す（内部クローンで `FOR UPDATE` は外す）

### SELECT 列リスト（`select` / `addSelect` / `selectRaw`）

SELECT 列だけ **`select` = 置き換え**、**`add*` = 追記** という分かれ方をします（`where` / `orderBy` のように追記のみ、とは異なります）。

| メソッド | 挙動 |
|---------|------|
| **`select(...)`** | 列リストを**丸ごと置き換え**。これまでの `select()` / `addSelect()` / `selectRaw()` で積んだ列は捨てられます。 |
| **`addSelect(...)`** | エスケープ済みの列名を**追記**。 |
| **`selectRaw(...)`** | 生 SQL 式を**追記**（`addSelect` と同じ追記型）。 |

**`clearSelect()` はありません。** 列をいったん空にして作り直すときも **`select(...)`** で新しいリストを指定します（引数なしの `select()` で空にしてから `selectRaw` する、といった使い方も可能。`executeCountQuery()` がその例です）。

`sqlQuery()` の `Query` はコンストラクタで **`select("{$table}.*")`** 済みです。列を変えたいときは先に **`select(...)`** で置き換えてください。`addSelect()` / `selectRaw()` だけだと既定の `table.*` が残り、`SELECT table.*, expr ...` になり得ます。

```php
// sqlQuery() の既定 table.* を捨てて列を指定
$users = $repository->sqlQuery()
    ->select('u.id', 'u.name')
    ->getResult();

// select() のあとに追記
$users = $repository->sqlQuery()
    ->select('u.id')
    ->addSelect('u.name', 'u.email')
    ->selectRaw('COUNT(*) AS cnt', [])
    ->getResult();

// 再度 select() すると、それまでの列はすべて置き換わる
$builder = $repository->sqlQuery()
    ->select('u.id')
    ->addSelect('u.name')
    ->select('u.email');          // 残るのは u.email のみ
```

### 使用例

```php
// 複数のWHERE条件
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->where('age', 25, '>=')
    ->getResult();

// WHERE IN句
$userIds = [1, 2, 3, 4, 5];
$users = $repository->sqlQuery()
    ->whereIn('id', $userIds)
    ->getResult();

// 複雑なWHERE条件（OR条件など）
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->whereRaw('(age > :min_age OR score > :max_score)', [
        ':min_age' => 25,
        ':max_score' => 90
    ])
    ->getResult();

// JOIN句
$users = $repository->sqlQuery()
    ->select('u.*', 'p.name AS profile_name')
    ->from('users u')
    ->joinRaw('LEFT JOIN profiles p ON u.id = p.user_id')
    ->where('u.status', 'active')
    ->getResult();

// WITH句（CTE）
$users = $repository->sqlQuery()
    ->withRaw("recent_orders AS (SELECT user_id, amount FROM orders WHERE order_date > '2024-01-01')")
    ->select('u.*', 'ro.amount')
    ->from('users u')
    ->joinRaw('LEFT JOIN recent_orders ro ON u.id = ro.user_id')
    ->getResult();

// ページネーション
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->offset(40)
    ->getResult();

// 件数取得
$count = $repository->sqlQuery()
    ->where('status', 'active')
    ->limit(10)->offset(20) // これらの設定は無視する
    ->executeCountQuery();
```

### Raw SELECT / FROM

**`selectRaw()`** と **`fromRaw()`** は、式・スカラサブクエリ・派生テーブルが必要なときに使います。`whereRaw` / `joinRaw` と同様、**バインドはクエリ全体で 1 つの袋**にマージされ、最終 SQL に対して **`whereIn()`** や **`:_EXPAND_`** の展開が効きます。

- **`selectRaw($expr, $bindings)`** — いまの `select()` の列リストの**後ろに追加**します。`sqlQuery()` の既定の `table.*` を捨てたいときは、先に `select(...)` で列を置き換えてください。さもないと `SELECT *, expr` のようになり得ます。
- **`fromRaw($fragment, $bindings)`** — `FROM` の本体を断片で置き換えます（サブクエリなら括弧とエイリアスまで含める、例: `(SELECT …) AS t`）。断片内の **`:_EXPAND_`** も展開されます。[IN句の配列展開](#in句の配列展開) と同じルールで `setParameters()` します（例: SQL に `:_EXPAND_uid`、パラメータに `['uid' => [1, 2, 3]]`）。

```php
// SELECT リストに相関スカラサブクエリ
$rows = $repository->sqlQuery()
    ->select('o.id', 'o.total')
    ->selectRaw(
        '(SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS line_count'
    )
    ->from('orders o')
    ->getResult();

// FROM に派生テーブル + 内部で IN 展開
$rows = $repository->sqlQuery()
    ->select('sub.id')
    ->fromRaw('(SELECT id FROM users WHERE id IN (:_EXPAND_uid)) AS sub')
    ->setParameters(['uid' => $userIds])
    ->getResult();
```

UNION などビルダで素直に表しにくい形は、生 SQL を組み立てて **`fetch()`** で実行するのが無難です。

### DISTINCT, GROUP BY, HAVING, FOR UPDATE

SQL 上の順序は **WHERE → GROUP BY → HAVING → ORDER BY → LIMIT/OFFSET → FOR UPDATE** です。

- **`distinct()`** — `SELECT` の直後に `DISTINCT` を付けます。既定では付けません。同じビルダーで `distinct(false)` とすると再度オフにできます。
- **`groupBy()`** — 1 回の呼び出しで複数列を渡せます。呼び出しを重ねると列が末尾に追加されます（例: `groupBy('a', 'b')->groupBy('c')` → `GROUP BY a, b, c`）。
- **`having()` / `havingRaw()`** — プレースホルダの扱いは `where()` / `whereRaw()` と同様で、バインドは WHERE と同じパラメータ集合にマージされます。集約条件は `havingRaw('COUNT(*) > :n', [':n' => $min])` のように書くと DB 間で無難です。SELECT のエイリアスを HAVING で使えるかはエンジンや設定次第です（PostgreSQL や厳格なモードでは使えないことがあります）。
- **`forUpdate()`** — **PostgreSQL / MySQL 等**の行ロックヒントです。**SQLite** では同様に使えないため、SQLite 向けビルドでは付けないでください。`executeCountQuery()` は内部でクローンに対し **`forUpdate(false)`** をかけ、`COUNT(*)` にロック句が残らないようにしています。

```php
// DISTINCT（JOIN で親行が重複する場合など）
$rows = $repository->sqlQuery()
    ->select('u.id', 'u.name')
    ->from('users u')
    ->joinRaw('INNER JOIN orders o ON o.user_id = u.id')
    ->distinct()
    ->getResult();

// GROUP BY + HAVING
$stats = $repository->sqlQuery()
    ->select('status', 'COUNT(*) AS cnt')
    ->from('users')
    ->groupBy('status')
    ->havingRaw('COUNT(*) >= :min_cnt', [':min_cnt' => 5])
    ->orderBy('status')
    ->getResult();

// FOR UPDATE（トランザクション内、PostgreSQL / MySQL の例）
$users = $repository->sqlQuery()
    ->where('id', $id)
    ->limit(1)
    ->forUpdate()
    ->getResult();
```

### 追記句のリセット（`clear*`）

`where` / `joinRaw` / `groupBy` / `having` / `orderBy` および各 `*Raw` は、呼ぶたびに**追記**されます。同じビルダーインスタンス上で一度積んだ句を捨てて作り直すには、対応する **`clear*`** メソッドを使います。

| メソッド | クリア対象 | 利用できるビルダー |
|---------|-----------|-------------------|
| `clearWhere()` | WHERE 条件 | `Query`, `Update`, `Delete` |
| `clearSet()` | SET 句 | `Update` のみ |
| `clearJoin()` | JOIN 句 | `Query` のみ |
| `clearGroupBy()` | GROUP BY 列 | `Query` のみ |
| `clearHaving()` | HAVING 条件 | `Query` のみ |
| `clearOrderBy()` | ORDER BY 式 | `Query` のみ |
| `clearParameters()` | バインドパラメータ袋 | `Query`, `Update`, `Delete` |

**補足:**

- **`clearWhere()` / `clearHaving()` / `clearSet()`** は SQL 断片だけを消します。以前の呼び出しで追加したバインド値は **`clearParameters()`** まで残ることがあります。
- **置き換え型**の句には `clear*` はありません。例: **`select()` は列リストを置き換え**（`clearSelect()` はない）、`from()` / `fromRaw()` は FROM を置き換え、`limit(null)` / `offset(null)` で LIMIT/OFFSET を外せ、`forUpdate(false)` で `FOR UPDATE` をオフにできます。
- 典型的な用途: リポジトリフック等で既定の句が付いたあと、アプリ側で一部だけ差し替える。件数取得の `executeCountQuery()` は内部クローンで `select()` により列を置き換えてから `selectRaw('COUNT(*) …')` する、といったパターンです。

```php
// 既に ORDER BY があるクエリの並び順を差し替える
$users = $repository->sqlQuery()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->clearOrderBy()
    ->orderBy('id', 'ASC')
    ->getResult();

// 先に付けた JOIN を外し、別の JOIN に付け替える
$rows = $repository->sqlQuery()
    ->joinRaw('INNER JOIN orders o ON o.user_id = users.id')
    ->clearJoin()
    ->joinRaw('LEFT JOIN profiles p ON p.user_id = users.id')
    ->getResult();

// Update でも WHERE をリセットできる（Update/Delete は WhereTrait 共通）
$repository->sqlUpdate()
    ->set('status', 'inactive')
    ->where('last_login', '2020-01-01', '<')
    ->clearWhere()
    ->setId(1)
    ->execute();
```

---

## Insert（INSERT文）

Repositoryから`sqlInsert()`メソッドでInsertオブジェクトを取得します。

### 基本的な使い方

```php
$insert = $repository->sqlInsert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active'
]);
$insert->execute();
```

### メソッド一覧

- `data(array $data)` - INSERT 列マップを**置き換え**（Update の `data()` と同じ契約）
- `getSql()` - 生成されたSQLを取得
- `getParameters()` - バインディングパラメータを取得
- `execute()` - SQLを実行

### 使用例

```php
// 基本的なINSERT
$repository->sqlInsert([
    'name' => 'Alice',
    'email' => 'alice@example.com'
])->execute();

// 後から列マップを指定（置き換え。Insert インスタンスの使い回しにも使える）
$insert = $repository->sqlInsert([]);
$insert->data([
    'name' => 'Bob',
    'email' => 'bob@example.com'
]);
$insert->execute();
```

**Insert** と **Update** の `data()` は共通ルールです。**この文で書き込む列マップを丸ごと指定（置き換え）**します。Update で SET を追記したいときは `data()` のあと `set()` / `setRaw()` を使います。

---

## Update（UPDATE文）

Repositoryから`sqlUpdate()`メソッドでUpdateオブジェクトを取得します。

**重要**: Updateは必ずWHERE条件が必要です。WHERE条件がない場合は`RuntimeException`がスローされます。

### 基本的な使い方

```php
$update = $repository->sqlUpdate();
$update->set('name', 'Jane Doe')
    ->setId(1)  // 主キーでWHERE条件を設定
    ->execute();
```

### メソッド一覧

- `set(string $column, mixed $value)` - SET 句を**追記**
- `setRaw(string $sqlSnippet, array $bindings = [])` - 生の SET 句を**追記**（SQL 関数など）
- `data(array $data)` - SET 列マップを**置き換え**（`Update` では主キーは自動除外）
- `clearSet()` - SET 句をすべてクリア
- `setId(int|string $id)` - 主キーでWHERE条件を設定
- `where(string $column, mixed $value, string $operator = '=')` - WHERE条件を追加
- `whereIn(string $column, array $values)` - WHERE IN条件を追加
- `whereNotIn(string $column, array $values)` - WHERE NOT IN条件を追加
- `whereRaw(string $sql_snippet, array $bindings = [])` - 生のWHERE句を追加
- `clearWhere()` - WHERE 条件をすべてクリア
- `clearParameters()` - バインドパラメータ袋を空にする
- `getSql()` - 生成されたSQLを取得
- `getParameters()` - バインディングパラメータを取得
- `execute()` - SQLを実行（WHERE条件が必須）

### SET 句（`set` / `data` / `clearSet`）

| メソッド | 挙動 |
|---------|------|
| **`set()` / `setRaw()`** | SET 断片を**追記** |
| **`data()`** | SET 列マップを**置き換え**（Insert の `data()` と同契約。`Update::data()` は主キーを除外） |
| **`clearSet()`** | SET 句をすべてクリア |

### 使用例

```php
// 主キーで更新
$repository->sqlUpdate()
    ->set('name', 'Alice')
    ->setId(1)
    ->execute();

// 複数のカラムを一括更新
$repository->sqlUpdate()
    ->data([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'status' => 'active'
    ])
    ->setId(2)
    ->execute();

// SQL関数を使用（setRaw）
$repository->sqlUpdate()
    ->setRaw('updated_at = CURRENT_TIMESTAMP')
    ->set('name', 'Charlie')
    ->setId(3)
    ->execute();

// 複雑なWHERE条件
$repository->sqlUpdate()
    ->set('status', 'inactive')
    ->where('last_login', '2020-01-01', '<')
    ->whereIn('id', [10, 20, 30])
    ->execute();

// 注意: data()メソッドは主キーを自動的に除外します
$repository->sqlUpdate()
    ->data([
        'id' => 999,  // これはSET句に含まれません
        'name' => 'David'
    ])
    ->setId(4)  // WHERE句で使用されます
    ->execute();
```

---

## Delete（DELETE文）

Repositoryから`sqlDelete()`メソッドでDeleteオブジェクトを取得します。

**重要**: Deleteは必ずWHERE条件が必要です。WHERE条件がない場合は`RuntimeException`がスローされます。

### 基本的な使い方

```php
$delete = $repository->sqlDelete();
$delete->setId(1)  // 主キーでWHERE条件を設定
    ->execute();
```

### メソッド一覧

- `setId(int|string $id)` - 主キーでWHERE条件を設定
- `where(string $column, mixed $value, string $operator = '=')` - WHERE条件を追加
- `whereIn(string $column, array $values)` - WHERE IN条件を追加
- `whereNotIn(string $column, array $values)` - WHERE NOT IN条件を追加
- `whereRaw(string $sql_snippet, array $bindings = [])` - 生のWHERE句を追加
- `clearWhere()` - WHERE 条件をすべてクリア
- `clearParameters()` - バインドパラメータ袋を空にする
- `getSql()` - 生成されたSQLを取得
- `getParameters()` - バインディングパラメータを取得
- `execute()` - SQLを実行（WHERE条件が必須）

### 使用例

```php
// 主キーで削除
$repository->sqlDelete()
    ->setId(1)
    ->execute();

// 複数の条件で削除
$repository->sqlDelete()
    ->where('status', 'inactive')
    ->where('last_login', '2020-01-01', '<')
    ->execute();

// WHERE IN句で削除
$repository->sqlDelete()
    ->whereIn('id', [10, 20, 30])
    ->execute();
```

---

## IN句の配列展開

IN句で配列を使用する場合、DecaORMは自動的にプレースホルダーを展開します。これにより、SQLインジェクションを防ぎながら、動的な数の値を安全に扱えます。

### 基本的な使い方（whereInメソッド）

```php
$userIds = [1, 2, 3, 4, 5];
$users = $repository->sqlQuery()
    ->whereIn('id', $userIds)
    ->getResult();
```

`whereNotIn()`も同様に配列展開されます。

```php
$excludedUserIds = [10, 20, 30];
$users = $repository->sqlQuery()
    ->whereNotIn('id', $excludedUserIds)
    ->getResult();
```

内部的には、以下のように展開されます：

```sql
-- 生成されるSQL
WHERE id IN (:_EXPAND_id_0_0, :_EXPAND_id_0_1, :_EXPAND_id_0_2, :_EXPAND_id_0_3, :_EXPAND_id_0_4)

-- パラメータ
[
    '_EXPAND_id_0_0' => 1,
    '_EXPAND_id_0_1' => 2,
    '_EXPAND_id_0_2' => 3,
    '_EXPAND_id_0_3' => 4,
    '_EXPAND_id_0_4' => 5,
]
```

### 手動でのIN句展開（:_EXPAND_マーカー）

JOIN句など、`whereIn()`メソッドが使えない場所でIN句を使いたい場合は、`:_EXPAND_`プレフィックス付きのプレースホルダーを使用し、`setParameters()`で配列を渡します。

```php
$userIds = [1, 2, 3];

$query = $repository->sqlQuery()
    ->select('u.*', 'o.amount')
    ->from('users u')
    ->joinRaw('LEFT JOIN orders o ON u.id = o.user_id AND o.user_id IN (:_EXPAND_user_id)')
    ->whereIn('u.id', $userIds)
    ->setParameters(['user_id' => $userIds]);  // JOIN句用のパラメータ

$users = $query->getResult();
```

**重要ポイント**:

1. **プレースホルダー名**: `:_EXPAND_user_id` の形式で指定（コロン付き）
2. **パラメータ名**: `setParameters()`では`user_id`（コロンなし、`_EXPAND_`プレフィックスなし）を指定
3. **自動展開**: 配列が自動的に複数のプレースホルダーに展開される

### 展開の仕組み

```php
// 1. SQLテンプレートで :_EXPAND_user_id を使用
->joinRaw('... AND user_id IN (:_EXPAND_user_id)')

// 2. setParameters()で配列を渡す
->setParameters(['user_id' => [1, 2, 3]])

// 3. 自動的に展開される
// SQL: ... AND user_id IN (:_EXPAND_user_id_0, :_EXPAND_user_id_1, :_EXPAND_user_id_2)
// パラメータ: [
//     '_EXPAND_user_id_0' => 1,
//     '_EXPAND_user_id_1' => 2,
//     '_EXPAND_user_id_2' => 3,
// ]
```

### 空の配列の場合

`whereIn()`に空の配列を渡した場合、常に結果を返さない安全な条件が追加されます：

```php
$users = $repository->sqlQuery()
    ->whereIn('id', [])  // 空の配列
    ->getResult();
// 生成されるSQL: WHERE (1 = 0)
```

`whereNotIn()`に空の配列を渡した場合は、除外対象がないため常に真となる条件が追加されます：

```php
$users = $repository->sqlQuery()
    ->whereNotIn('id', [])
    ->getResult();
// 生成されるSQL: WHERE (1 = 1)
```

### 複数のIN句を使用する場合

```php
$userIds = [1, 2, 3];
$categoryIds = [10, 20, 30];

$query = $repository->sqlQuery()
    ->whereIn('user_id', $userIds)
    ->whereIn('category_id', $categoryIds)
    ->getResult();
```

各IN句は独立して展開され、プレースホルダー名の衝突は自動的に回避されます。

---

## 注意事項

### 1. Update/DeleteのWHERE条件は必須

UpdateとDeleteは必ずWHERE条件を指定する必要があります。WHERE条件がない場合、`RuntimeException`がスローされます。

```php
// ❌ エラー: WHERE条件がない
$repository->sqlUpdate()
    ->set('name', 'Alice')
    ->execute();  // RuntimeException

// ✅ 正しい: WHERE条件を指定
$repository->sqlUpdate()
    ->set('name', 'Alice')
    ->setId(1)
    ->execute();
```

### 2. Updateの主キーは自動除外

`Update::data()`メソッドで主キーを含めても、SET句には含まれません。主キーは更新対象外です。

```php
$repository->sqlUpdate()
    ->data([
        'id' => 999,  // SET句には含まれない
        'name' => 'Alice'
    ])
    ->setId(1)  // WHERE句で使用
    ->execute();
```

### 3. プレースホルダーの命名規則

- `where()`や`having()`や`set()`で自動生成されるプレースホルダーは、カラム名とカウンターから生成されます
- 手動でIN句展開する場合は、`:_EXPAND_`プレフィックスを使用
- `setParameters()`では、`_EXPAND_`プレフィックスを付けずに指定

### 4. SQLインジェクション対策

- カラム名やテーブル名は、必ずコード内で指定してください（ユーザー入力から直接使用しない）
- 値は必ずプレースホルダーを使用してください
- `whereRaw()`や`setRaw()`を使用する場合は、バインディングパラメータを必ず使用してください

```php
// ❌ 危険: SQLインジェクションのリスク
$column = $_GET['column'];  // ユーザー入力
$query->whereRaw("{$column} = 'value'");

// ✅ 安全: カラム名はコード内で指定
$query->where('status', 'active');

// ✅ 安全: 値はプレースホルダーを使用
$query->whereRaw('created_at > :date', [':date' => $userInput]);
```

### 5. パラメータの取得タイミング

`getSql()`や`getParameters()`を呼び出すと、IN句の展開処理が実行されます。複数回呼び出すと、同じ結果が返されます（キャッシュされます）。

```php
$query = $repository->sqlQuery()
    ->whereIn('id', [1, 2, 3]);

$sql1 = $query->getSql();        // 展開処理が実行される
$sql2 = $query->getSql();        // キャッシュされた結果が返される

$params1 = $query->getParameters();  // 展開処理が実行される（既に実行済みの場合はキャッシュを使用）
```

### 6. SQLログのパラメーターマスク

`OrmManager` に `setSqlParamMasker()` を設定すると、SQLログの `params` だけをマスクできます。
`sql` 本文はそのまま保持されるため、クエリ解析性を維持しながら機密値漏えいを抑制できます。

```php
use WScore\DecaORM\OrmManager;
use WScore\DecaORM\Sql\KeyBasedSqlParamMasker;

$manager = OrmManager::initialize($container)
    ->setLogger($logger)
    ->setSqlParamMasker(new KeyBasedSqlParamMasker([
        'password', 'token', 'secret', 'authorization', 'api_key', 'email',
    ]));
```

- マスク対象はキー名で判定されます（大文字小文字は区別しません）
- ネストした配列にも再帰的に適用されます
- `setSqlParamMasker()` を設定しない場合は、従来どおりマスクなしです

---

## まとめ

- **Query**: SELECT文を構築し、`getResult()`でエンティティを取得。必要に応じて `distinct()`、`groupBy()`、`having()` / `havingRaw()`、`forUpdate()` を利用
- **Insert**: INSERT文を構築し、`execute()`で実行
- **Update**: UPDATE文を構築（WHERE必須）、`execute()`で実行
- **Delete**: DELETE文を構築（WHERE必須）、`execute()`で実行
- **IN句展開**: `whereIn()`メソッド、または`:_EXPAND_`マーカーと`setParameters()`を使用
- **安全性**: 常にプレースホルダーを使用し、カラム名はコード内で指定

