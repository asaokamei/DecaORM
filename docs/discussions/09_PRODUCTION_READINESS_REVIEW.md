# 本番運用に向けたレビュー（Production Readiness Review）

- 実施日: 2026-06-12
- 対象バージョン: 0.5.3
- 確認方法: src 全コアファイルの精読（SQLビルダ、リポジトリ、ハイドレータ、identity map、dirty tracking、hooks、リレーションローダ）、テストのローカル実行（SQLiteで194件すべてパス）、CI構成の確認（PHP 8.1〜8.4 × SQLite/MySQL/PostgreSQL）

## 総評

SQL組み立て層の設計は堅実でテストも厚い。一方、「公開Webサービスの本番」を想定すると、**ログへの機密値漏えい・fill()の既定動作・長時間プロセスでの状態管理**の3点はリリース前に手当てすべき。

---

## 1. リリース前に対応を推奨する点

### (a) SQLログに機密値が平文で出る／マスカーが実質効かない

`SqlLogger` は成功時もバインドパラメータを全部ログに渡し、スロークエリはWARNINGで出力されるため、本番でもパスワードハッシュやトークンがログ基盤に流れる。対策用の `KeyBasedSqlParamMasker` はあるが、**キーの完全一致**で照合している（`src/Sql/KeyBasedSqlParamMasker.php` 32-33行）。

ところが実際にORMが生成するプレースホルダ名は `password_0`（Insert/Where）や `set_password_1`（`UpdateBuilder::set()`）のように**カウンタ付き**なので、`['password']` を指定しても保存経由のクエリではマスクされない。`SqlLoggerTest` も手書きの完全一致キーしか検証しておらず、この食い違いに気づきにくい。前方一致または「カラム名部分の抽出」での照合に変えるのが安全。

### (b) `fill()` の既定がマスアサインメント全許可

`src/Trait/EntityTrait.php` 80-81行: `$fillable` 未定義なら PK / created_at / updated_at 以外の**全カラムが書き込み可能**。`$user->fill($_POST)` のような典型コードで `is_admin` や `role` を外部入力から書けてしまう。Laravelが「既定で全拒否（guarded='*'）」なのと逆の方針。公開プロジェクトに使うなら:

1. 既定を拒否に変える、または
2. 各エンティティで `$fillable` を必須運用にする

のどちらかを決めてREADMEに明記すべき。

### (c) シングルトン＋無制限キャッシュは長時間プロセスで危険

`OrmManager` はプロセス全体の静的シングルトンで、`EntityCache`（identity map）と `DirtyTracker` を抱え込む。

- **EntityCache は無制限に成長**し、クリアは手動。`DirtyTracker` のスナップショットも削除時以外解放されない。バッチ処理や Octane / RoadRunner / FrankenPHP worker のような常駐型では、メモリリークに加えて**前リクエストのエンティティ（別ユーザー・別テナントのデータ）が identity map 経由で返る**事故につながる。READMEはリクエスト毎の `initialize()` に触れているが、忘れたときの安全装置がない。
- `DirtyTracker` は `spl_object_id` をキーにしつつ**エンティティへの参照を保持しない**ため、GCされたオブジェクトのIDが再利用されると、新しいエンティティが他人のスナップショットを引き継ぎ、差分UPDATEが誤る可能性がある（identity mapに乗らない `fetchStream` 由来や手動生成のエンティティで顕在化しうる）。`WeakMap` への置き換えが妥当。
- `OrmManager::getDateTimeImmutable()` は**最初の呼び出し時刻を永久にキャッシュ**し、リポジトリもコンストラクタで `$this->now` を固定する。常駐プロセスでは created_at / updated_at が起動時刻で凍結する。

php-fpm のリクエスト毎プロセスで運用するなら実害は小さいが、デプロイ形態の前提をREADMEに明記し、可能なら `now` のキャッシュはやめるべき。

### (d) Repository hooks と ManyToMany の組み合わせが破綻する

`LoadManyToMany::newJoinTableQuery()`（`src/Relation/LoadManyToMany.php` 287-289行）は `sourceRepository->sqlQuery()` を使うため、**hooks の `beforeQuery` が中間テーブルのクエリにも適用される**。

- `TenantScopeHooks` を使うと中間テーブルに `WHERE tenant_id = ?` が付く（カラムがなければSQLエラー、あれば意図しない絞り込み）。`SoftDeleteHooks` も同様に `deleted_at IS NULL` が付く。
- 逆に `ManyToManyTrait::syncManyToMany()` の生SQLは **hooks を一切通らない**ので、テナント境界の検査なしに中間テーブルを書き換えられる。
- 同traitの生SQLは識別子クォートもされず、他のビルダと非対称。

マルチテナント運用をhooksで実現するつもりなら要修正。

---

## 2. 正確性・信頼性の懸念（中優先）

- **再取得で未保存の変更が黙って消える**: `hydrateManagedFromRow()` はidentity mapにあるエンティティへDB値を上書きする。編集途中のエンティティが、別の箇所での `find()` により無警告で巻き戻る。さらに `applyRowDataToEntity()`（`src/Trait/HydratorTrait.php` 44-46行）は `isset()` 判定なので **DBでNULLのカラムはスキップされ、古い非NULL値が残る**（NULL化された変更が反映されない実バグ）。
- **型変換層がない**: ハイドレーションで全値を `(string)` キャストしてセットする。int/string プロパティはPHPの型強制で動くが、`DateTimeImmutable`・bool・json・enum 型のプロパティは扱えず、すべてゲッターでの手動変換になる。エンティティ数が増えると変換コードの重複が保守負担になる。
- **`transaction()` の制約**: ネスト（savepoint）不可で、二重 `beginTransaction()` は例外。全例外を `RuntimeException` でラップするため、`VersionColumnHooks` が投げる `OptimisticLockException` を呼び出し側で型キャッチできず `getPrevious()` を掘る必要がある。また静的メソッドでシングルトンのPDO固定なので、`setUpRepository()` に個別PDOを渡したリポジトリはトランザクション対象外。
- **非auto-increment PKの `isNew()` 判定**: identity map に載っているかで INSERT/UPDATE を分岐するため、別プロセスで作られた既存行のエンティティを `save()` すると INSERT を試みて重複キーエラーになる。
- **`setRaw()`/`getRaw()` がtypoを握りつぶす**: 存在しないプロパティ名は無言でno-op / null返却。リレーション名やカラム名の打ち間違いが実行時に発見できない。例外を投げるか、少なくともログを出す方が運用は楽。
- **小さな非効率**: `LoadManyToMany::loadBatch()` は同一の中間テーブルクエリを2回実行している（`getRelatedIdsFromJoinTableBatch` と `groupRelatedIdsByEntityId`）。MorphTo の親解決はドキュメント記載どおり1件ずつでN+1。

---

## 3. 不足機能（本番でアプリ側負担になるもの）

UoW・カスケード・Eager Loading の不在は文書化済みで「規律で運用する」判断は成立する。それ以外に:

- **ページネーション**: `executeCountQuery()` はあるが、limit/offset + 総件数 + ページ情報をまとめるヘルパがなく、公開サービスでは毎回手書きになる。
- **接続の堅牢性**: 再接続（MySQLの "server has gone away"）、リトライ、リードレプリカ分離の仕組みがない。常駐型でなければ優先度は下がる。
- **スキーマ/マイグレーション**: 範囲外という設計だろうが、Phinx等の併用をREADMEに案内すると親切。

---

## 4. 保守性

- **0.x で破壊的変更が続いている**（直近の0.5.3でも `RepositoryInterface` にメソッド追加）。公開プロジェクト側では**バージョンをexact pin**し、1.0で安定化の線を引くことを推奨。
- **静的解析がない**: phpstan/psalm の設定がなく、`declare(strict_types=1)` も未使用。`getRaw(): mixed` 中心の設計は型エラーが実行時まで出ないので、phpstan（level 6+）導入の効果が大きい。phpunit も `^9.5`（既にEOL）なので10/11系への更新を。
- **バス係数1**: 公開プロジェクトの依存にするなら「最悪フォークして自分で直せる規模か」が基準。srcは30ファイル強で読みやすく、テストが194件・3DB×4PHPのCIで守られているのは大きな強みで、この基準は満たしている。

---

## まとめ

SQLインジェクション対策（識別子検証＋プレースホルダ、Raw系の明示と警告）、楽観ロック、hooks、dirty tracking、テストカバレッジといった基礎体力は趣味プロジェクトの水準を明らかに超えている。「公開・本番」に進むなら、以下4つは具体的なバグ/事故要因なので先に潰すこと:

1. ログマスカーのプレースホルダ名不一致（1-a）
2. `fill()` の既定全許可（1-b）
3. hooks × ManyToMany の不整合（1-d）
4. NULLカラムが再ハイドレートされない `isset()` バグ（2の1点目）

加えて、**デプロイ形態（FPM前提か常駐型か）と identity map のライフサイクルをREADMEに明文化**することを推奨する。
