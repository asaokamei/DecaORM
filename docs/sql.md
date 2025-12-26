# SQL ビルダー マニュアル

DecaORMのSQLビルダーを使用して、型安全で柔軟なSQLクエリを構築できます。

## 目次

- [Query（SELECT文）](#queryselect文)
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
    ->orderBy('created_at DESC')
    ->limit(10)
    ->getResult();
```

### メソッド一覧

- `select(string ...$columns)` - SELECT句を指定
- `from(string $table)` - FROM句を指定（通常は自動設定）
- `where(string $column, mixed $value, string $operator = '=')` - WHERE条件を追加
- `whereIn(string $column, array $values)` - WHERE IN条件を追加
- `whereRaw(string $sql_snippet, array $bindings = [])` - 生のWHERE句を追加
- `joinRaw(string $raw_join_sql)` - JOIN句を追加
- `withRaw(string $cte_sql)` - WITH句（CTE）を追加
- `orderBy(string $column)` - ORDER BY句を指定
- `limit(?int $limit)` - LIMIT句を指定
- `offset(?int $offset)` - OFFSET句を指定
- `getResult()` - クエリを実行してエンティティの配列を取得
- `getCollection()` - クエリを実行してEntityCollectionを取得
- `executeCountQuery()` - COUNT(*)クエリを実行

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
    ->orderBy('created_at DESC')
    ->limit(20)
    ->offset(40)
    ->getResult();

// 件数取得
$count = $repository->sqlQuery()
    ->where('status', 'active')
    ->executeCountQuery();
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

- `data(array $data)` - 挿入するデータを指定
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

// データを後から追加
$insert = $repository->sqlInsert();
$insert->data([
    'name' => 'Bob',
    'email' => 'bob@example.com'
]);
$insert->execute();
```

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

- `set(string $column, mixed $value)` - SET句を追加
- `setRaw(string $sqlSnippet, array $bindings = [])` - 生のSET句を追加（SQL関数など）
- `data(array $data)` - 複数のSET句を一括指定（主キーは自動除外）
- `setId(int|string $id)` - 主キーでWHERE条件を設定
- `where(string $column, mixed $value, string $operator = '=')` - WHERE条件を追加
- `whereIn(string $column, array $values)` - WHERE IN条件を追加
- `whereRaw(string $sql_snippet, array $bindings = [])` - 生のWHERE句を追加
- `getSql()` - 生成されたSQLを取得
- `getParameters()` - バインディングパラメータを取得
- `execute()` - SQLを実行（WHERE条件が必須）

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
- `whereRaw(string $sql_snippet, array $bindings = [])` - 生のWHERE句を追加
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

- `where()`や`set()`で自動生成されるプレースホルダーは、カラム名とカウンターから生成されます
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

---

## まとめ

- **Query**: SELECT文を構築し、`getResult()`でエンティティを取得
- **Insert**: INSERT文を構築し、`execute()`で実行
- **Update**: UPDATE文を構築（WHERE必須）、`execute()`で実行
- **Delete**: DELETE文を構築（WHERE必須）、`execute()`で実行
- **IN句展開**: `whereIn()`メソッド、または`:_EXPAND_`マーカーと`setParameters()`を使用
- **安全性**: 常にプレースホルダーを使用し、カラム名はコード内で指定

