# Many to Many and N+1 Discussion

2025.12.17
with Google Gemini 3 Pro

[Google Gemini](https://gemini.google.com/app/f7e595420238be35)

# あぁ、この会話全体をマークダウンに変換できないかな？　何処かに保存しておきたい。というかレポジトリに保存しているんだが…

## DecaORM 設計メモとアーキテクチャ方針

本ドキュメントは、シンプルかつ実用的なPHP用ORM「DecaORM」の設計方針と実装仕様についての検討ログをまとめたものである。

### 1. コア・コンセプト (Core Concepts)

- Simple & Explicit (シンプルで明示的):
  - 「魔法」や「暗黙の挙動」を極力排除する。 
  - 開発者がコードを見ただけで何が起きるか予測できるようにする。

- No UnitOfWork (UoWなし):
  - 保存順序の自動解決や、変更の遅延書き込み（flush）は実装しない。 
  - ユーザーが明示的に保存順序を制御する（親を保存 → ID確定 → 子を保存）。 
  - これにより、複雑なトポロジカルソートやダーティチェックの複雑性を回避する。

- 1 Table = 1 Repository:
  - リポジトリは原則として「自身の管理するテーブル」のみを読み書きする。
  - JOIN を乱発せず、関連データの取得は Batch Loading（後述）で行う。

- POPO Entity:
  - エンティティはロジックを持たず、プロパティとメタデータ（Attributes）のみを持つ Plain Old PHP Object とする。 
  - Lazy Loading用のProxyなどは導入しない。

## 2. 多対多 (Many-to-Many) の実装方針

UnitOfWorkを持たない環境下で、実用的な多対多の保存処理を実現する。

### 命名規則の決定

多対多リレーションには `ManyToMany` アトリビュートを使用する。

**理由:**
- `BelongsTo` は「外部キーを持つ側」を意味するが、多対多ではどちらのエンティティも自分のテーブルに外部キーを持たない（中間テーブルが両方のIDを持つ）
- `ManyToMany` はリレーションの種類を直接表現し、既存の `BelongsTo`/`HasMany` との混同を避けられる
- シンプルで直感的で、Doctrine ORMなどでも一般的な命名

### 保存・更新ロジック

- IDリストによる同期 (sync):
  - Webフォーム等からの入力を想定し、「最終的にこのIDリストの状態にする」という sync メソッドを提供する。
- 実装方式: メモリ上で差分計算をするのではなく、DBを正（Source of Truth）とする。
  1. SELECT で現在の関連IDを取得。
  2. 差分（追加分・削除分）を計算。
  3. INSERT / DELETE を発行。

**実装上の考慮事項:**
- トランザクション: `sync` 操作は単一のトランザクション内で実行すべきか？（ユーザーが制御する方針に合わせる）
- エラーハンドリング: 中間テーブルの制約違反（存在しないIDなど）の処理
- パフォーマンス: 大量のIDを同期する場合のバッチ処理の必要性

- Traitによる提供:
  - すべてのリポジトリに機能を持たせず、ManyToManyTrait を作成し、必要なリポジトリでのみ use する（Opt-in方式）。

```php
// 利用イメージ
$studentRepo->sync($student, 'courses', [101, 103]);
```

### 中間テーブルの扱い

単純な紐付け: 中間テーブル用のエンティティ・リポジトリは作成せず、`ManyToMany` Attribute定義のみで GenericRepository が処理する。中間テーブル名は `joinTable` パラメータで指定する。

情報を持つ中間テーブル: 紐付け以外のカラム（成績、登録日など）を持つ場合は、多対多として扱わず、「1対多 - 1対多」のエンティティとして明示的に実装する。

## 3. リレーション定義と読み込み (Read Strategy)

### Attribute定義と拡張性 (Escape Hatch)

基本的なリレーションは Attribute で定義するが、複雑な条件（複合キー、定数フィルタ、特殊なSQL）に対応するための「逃げ道」を用意する。

- 基本: foreignKey, localKey (配列による複合キー対応も可)。
- 拡張 (fetcher): 自動解決できない場合、リポジトリのメソッドに委譲する。

```PHP
class User {
// 複雑な条件はメソッドに丸投げ
#[HasMany(targetEntity: Order::class, fetcher: 'findRecentOrders')]
public array $recentOrders;
}
```

### N+1問題の解決 (Batch Loading)

リポジトリ間の独立性を保つため、JOIN ではなく WHERE IN による一括取得を行う。

- fill メソッド:
  - エンティティのリスト（または単体）を受け取り、指定されたリレーションを一括ロードする。
  - 内部で WHERE foreign_key IN (...) を発行し、メモリ上でマッピングする。

- 戻り値によるチェーン:
  - ドット記法（posts.comments）のような文字列解析は行わない。
  - fill は 「ロードした子エンティティの配列」 を返す。 
  - ネストしたロードは、その戻り値を使ってユーザーが記述する。

```PHP
// 1. Author -> Post をロード (戻り値でPost一覧を受け取る)
$posts = $authorRepo->fill($authors, 'posts');

// 2. Post -> Comment をロード (フィルタリングなども可能)
$postRepo->fill($posts, 'comments');
```

## 4. 全体アーキテクチャ構成
### コンポーネント

1. Entity (POPO):
  - データ保持のみ。#[Table], #[Column], #[HasMany], #[ManyToMany] 等のAttributes記述。

2. MetadataManager:
  - Reflectionを用いてAttributesを解析・キャッシュする。

3. Database (PDO Wrapper):

  - SQL実行、トランザクション管理。

4. GenericRepository (Base Class):

  - CRUD (save, find, delete)。 
  - Batch Loading (fill)。 
  - Escape Hatch (fetcher 委譲ロジック)。

5. SpecificRepository:

  - ユーザー定義のリポジトリ。 
  - GenericRepository を継承。 
  - 必要に応じて use ManyToManyTrait。 
  - 複雑なクエリメソッドの実装。

## 5. 決定されたAPI仕様 (コード例)

### Entity

```PHP
#[Entity]
class Student {
    #[Id] public int $id;

    // 多対多定義
    #[ManyToMany(
        targetEntity: Course::class,
        joinTable: 'student_course',
        foreignKey: 'student_id',
        inverseForeignKey: 'course_id'
    )]
    public array $courses;
}
```

### Repository Usage
```PHP

// 保存 (順序はユーザー管理)
$studentRepo->save($student);
$courseRepo->save($course);

// 紐付け (Sync)
$studentRepo->sync($student, 'courses', [$course->id]);

// 読み込み (Batch Loading)
$students = $studentRepo->findAll();
$courses = $studentRepo->fill($students, 'courses'); // 戻り値でロード
```