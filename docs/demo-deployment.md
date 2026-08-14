# 一般公開デモ環境 デプロイ手順書

公開先: **`reservation-demo.kazeyui.com`**(Xserver)

このドキュメントは、完成済みの予約注文システムを、風結の一般向けホームページから一般公開デモとして体験できるようにするための、Xserverへのデプロイ手順・運用方法をまとめたものです。

**このドキュメントの時点ではまだXserverへの実配置・サブドメイン作成・DB作成・Cron登録は行っていません。** 実際にデプロイする際の手順書・チェックリストとして使うことを想定しています。

---

## 1. 本番前提

一般公開デモ環境は、以下の前提で構築します。

| 項目           | 値                                                                                                                            |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`      | `production`                                                                                                                  |
| `APP_DEBUG`    | `false`                                                                                                                       |
| `DEMO_MODE`    | `true`(このデプロイ専用の設定。通常の本番環境では`false`)                                                                     |
| DB             | MySQL または MariaDB(Xserver提供のもの)                                                                                       |
| 通信           | HTTPS                                                                                                                         |
| SMS送信実装    | `App\Services\Sms\LogSmsSender`(実SMSは送信しない。`DEMO_MODE`の仕組みでホワイトリスト電話番号にのみ認証コードを画面表示する) |
| キューワーカー | 不要(`QUEUE_CONNECTION=sync`に固定。詳細は下記「3. 必要な環境変数」参照)                                                      |
| 商品画像       | `storage:link` が必要                                                                                                         |

## 2. 一般公開デモ用アカウントの整理

| アカウント                            | 作成元                                               | 一般公開デモでの扱い                                                                             |
| ------------------------------------- | ---------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `farmer@example.com`(ローカル開発用)  | `DatabaseSeeder`(`php artisan migrate --seed`)       | **作成しない**。一般公開デモでは `migrate --seed` を実行せず、`php artisan migrate` のみ実行する |
| `demo-farmer@example.com`(公開デモ用) | `PortfolioDemoSeeder`(`php artisan demo:reset` 経由) | 作成する。ログイン情報は下記参照                                                                 |

### ログイン情報(README.mdの「ログイン情報」と同一)

`README.md`の「ポートフォリオ用デモデータ」→「ログイン情報」に記載のアカウントと、`database/seeders/PortfolioDemoSeeder.php`の実装(`DEMO_FARMER_EMAIL`・`DEMO_BUYER_EMAILS`・電話番号の生成規則)を突き合わせて確認済みで、**食い違いはありませんでした**。

- デモ農家: `demo-farmer@example.com` / パスワードはREADME記載の値
- デモ購入者(電話番号): `00000000001`〜`00000000005`

## 3. 必要な環境変数

`.env.example` を基準に、一般公開デモで実際に値を設定する必要がある項目を整理します。**値そのものはこのドキュメント・Gitに書きません。** 実際の値はXserver側の`.env`にのみ設定してください。

| 変数名                     | 設定する内容                                                        | 備考                                                                                                                      |
| -------------------------- | ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`                  | `production`                                                        |                                                                                                                           |
| `APP_DEBUG`                | `false`                                                             | trueのままだとエラー詳細が外部に露出する                                                                                  |
| `APP_URL`                  | `https://reservation-demo.kazeyui.com`                              | 商品画像URL(`storage`ディスクの`url`)にも使われる                                                                         |
| `APP_TIMEZONE`             | `Asia/Tokyo`                                                        | `.env.example`に既定値として設定済み(重複追加不要)。Scheduler(下記「5. Scheduler運用計画」の3処理)や配達予定日の計算など、日本時間を基準にする処理はすべて`config('app.timezone')`(=`APP_TIMEZONE`)を参照する |
| `APP_KEY`                  | `php artisan key:generate` で生成                                   | Git・このドキュメントに書かない                                                                                           |
| `DB_CONNECTION`            | `mysql`                                                             |                                                                                                                           |
| `DB_HOST`                  | Xserver提供のMySQL/MariaDBホスト名                                  |                                                                                                                           |
| `DB_PORT`                  | Xserver提供のポート番号                                             |                                                                                                                           |
| `DB_DATABASE`              | 一般公開デモ専用のDB名                                              | 他の用途のDBと共用しない                                                                                                  |
| `DB_USERNAME`              | 上記DB専用のユーザー名                                              |                                                                                                                           |
| `DB_PASSWORD`              | 上記DBユーザーのパスワード                                          | Git・このドキュメントに書かない                                                                                           |
| `SESSION_SECURE_COOKIE`    | `true`                                                              | HTTPS配信のため。`.env.example`にコメントとして追加済み                                                                   |
| `SESSION_DOMAIN`           | `reservation-demo.kazeyui.com` (先頭ドットの要否は実配置時に要確認) |                                                                                                                           |
| `SANCTUM_STATEFUL_DOMAINS` | `reservation-demo.kazeyui.com` を含める                             | **重要**。含めないと、SMS認証後にセッションが確立されずログイン状態にならない(第3段階のMySQL検証で実際に踏んだ問題と同種) |
| `DEMO_MODE`                | `true`                                                              | このデプロイ専用。他の環境では絶対にtrueにしない                                                                          |
| `DEMO_SMS_PHONE_NUMBERS`   | `00000000001,00000000002,00000000003,00000000004,00000000005`       | `PortfolioDemoSeeder`のデモ購入者電話番号と一致させる                                                                     |
| `MAIL_MAILER`              | `log`のままでよい                                                   | 実メール送信機能は使っていないため                                                                                        |
| `FILESYSTEM_DISK`          | `local`のままでよい                                                 | 商品画像は`Storage::disk('public')`を明示的に使っており、このデフォルト値には依存しない                                   |
| `QUEUE_CONNECTION`         | `sync`                                                              | 現在コード上`ShouldQueue`・`dispatch`等の非同期キュー処理は一切使用していないため、公開デモでは`sync`に固定し運用を単純化する(queue worker不要)。`database`を選ぶ積極的な理由は現状ない |

## 4. デプロイ手順(実行順)

Laravel 11の一般的な本番デプロイ手順に、このアプリ固有の手順(`storage:link`・`demo:reset`)を組み込んだ順序です。

1. **Composer依存関係のインストール**
    ```bash
    composer install --no-dev --optimize-autoloader
    ```
2. **`.env`の作成**：`.env.example`をコピーし、上記「3. 必要な環境変数」の値を設定する
3. **`APP_KEY`の生成**
    ```bash
    php artisan key:generate
    ```
4. **書き込み権限の確認**：`storage/`・`bootstrap/cache/`にWebサーバー実行ユーザーの書き込み権限があることを確認する
5. **マイグレーション**
    ```bash
    php artisan migrate --force
    ```
    (`migrate:fresh`は使わない。空の専用DBに対して初回実行するため、通常の`migrate`で全テーブルが作成される)
6. **`storage:link`**
    ```bash
    php artisan storage:link
    ```
7. **Laravel本番最適化**
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
8. **初期デモデータの作成**
    ```bash
    php artisan demo:reset
    ```
    (`db:seed --class=PortfolioDemoSeeder`は使わない。`production`環境では実行を拒否される安全設計のため。`demo:reset`は`DEMO_MODE=true`が設定されている場合のみ実行できる)
9. **Scheduler/Cron登録**：下記「5. Scheduler運用計画」を参照して`crontab`に1行登録する
10. **公開後確認**：下記「6. 公開後の確認項目」を参照する

### 手順3(`.env`変更)と7(config:cache)の順序について

`config:cache`を実行した状態で`.env`を変更しても反映されません。`.env`の内容を確定させてから`config:cache`を実行する順序(上記の並び)を守ってください。運用開始後に`.env`を修正する場合は、`php artisan optimize:clear`でキャッシュを解除してから反映し直してください(README.mdの「本番環境での注意事項」と同じ注意点です)。

## 5. Scheduler運用計画

### 現在の3つの日次処理(`routes/console.php`)

| コマンド                                          | 実行時刻   | timezone                     | 内容                                                               |
| ------------------------------------------------- | ---------- | ---------------------------- | ------------------------------------------------------------------ |
| `orders:update-product-sale-statuses`             | 毎日 00:05 | `APP_TIMEZONE`(`Asia/Tokyo`) | 販売シーズンの状態(準備中/販売中/販売終了)を日付に基づいて更新する |
| `orders:generate-delivery-confirmations`          | 毎日 07:00 | 同上                         | 配達予定日が3日後の受付済の注文に配達確認を作成し、農家へ通知する  |
| `orders:remind-unanswered-delivery-confirmations` | 毎日 17:00 | 同上                         | 未回答の配達確認について、農家へ再通知する                         |

### `demo:reset`の推奨実行時刻

**毎日 04:00(JST)を推奨します。**

理由:

- 00:05の「販売シーズン状態更新」から3時間55分、07:00の「配達確認生成」まで3時間の間隔を確保でき、互いの処理中に競合しない
- `PortfolioDemoSeeder`が作る注文・配達予定日は実行時点の「今日」を基準にした相対日付のため、日付が変わった直後(00:00以降)にリセットすることで、その日1日を通じて日付表示が正しい状態を保てる
- 深夜早朝は実際の閲覧者が最も少ないと見込まれる時間帯であり、リセット中(既存デモデータの削除→再作成)に閲覧者の操作と鉢合わせる可能性を最小化できる
- 17:00の処理とは半日以上離れており、影響しない

**今回はScheduler(`routes/console.php`)への実際の登録は行っていません。** 登録する際は、他の3処理と同様に`Schedule::command('demo:reset')->dailyAt('04:00')`の形を想定していますが、これは実装時に別途対応してください。

## 6. 公開後の確認項目(チェックリスト)

- [ ] `https://reservation-demo.kazeyui.com` にHTTPSでアクセスできる(HTTPは自動でHTTPSへリダイレクトされる、またはHTTPでアクセスできない)
- [ ] `APP_DEBUG=false`になっている(存在しないURL・不正なリクエストを試して、Laravelの詳細なエラー画面(黄色いデバッグ画面)やスタックトレースが表示されないことを確認する)
- [ ] 農家デモアカウント(`demo-farmer@example.com`)でログインできる
- [ ] 購入者デモ電話番号でSMS認証コードが画面に表示され、ログインできる(`DEMO_MODE`が正しく効いている確認)
- [ ] ホワイトリスト外の電話番号ではSMS認証コードが画面に表示されない(念のため)
- [ ] 商品画像が正しく表示される(`storage:link`の確認)
- [ ] 購入者が商品を注文できる
- [ ] 注文後、在庫数が正しく減少する
- [ ] 農家側で新規注文が確認できる
- [ ] `php artisan demo:reset`を手動実行し、正常終了・デモ初期状態への復元を確認する
- [ ] Scheduler(Cron)が登録されている場合、`storage/logs/laravel.log`等で実行ログが確認できる
- [ ] 通常のLaravelエラー画面(開発用のデバッグ画面)やスタックトレースが外部から見えない

## 7. 商品画像の素材管理・今後の運用方針

この予約注文システムは、制作当初から一般公開・ポートフォリオ利用を前提として制作されています。`database/seeders/demo-assets/products/` の以下6枚の商品画像も、加藤さんの記憶では「AI生成画像」または「公開利用可能な無料素材」として選定したものです。

| ファイル名         | 用途(商品名)       |
| ------------------ | ------------------ |
| `corn.jpg`         | 朝採れとうもろこし |
| `mini-tomato.jpg`  | 完熟ミニトマト     |
| `potato.jpg`       | ほくほくじゃがいも |
| `new-onion.jpg`    | 採れたて新玉ねぎ   |
| `sweet-potato.jpg` | 甘熟紅はるか       |
| `spinach.jpg`      | 朝採れほうれん草   |

### 現時点での記録状況

コードや過去のコミット履歴(`git log`)を調査しましたが、これら6枚について、生成履歴・素材配布元・利用規約URLなどの記録はリポジトリ内に残っていませんでした(追加時のコミット`feat: add portfolio demo product images`にも出典の記載なし。EXIF/IPTCメタデータも無し。全て1200×900pxで統一)。

このため、「利用権が法的に100%保証済み」と断定できる記録は現時点では存在しませんが、**制作時点での選定意図(AI生成または公開利用可能な無料素材)を踏まえ、この記録が追跡できないこと自体は、現時点ではXserverでの一般公開を止めるブロッカーとして扱いません**。

### 今後の運用方針

今後、デモ用画像を新たに追加・差し替える場合は、次の情報を記録に残す運用とします。

- AI生成画像かどうか(生成に使ったツール・サービス名)
- AI生成でない場合、素材サイト名・素材ページのURL
- 利用条件(商用利用可否・クレジット表記の要否など)
- 確認日

記録先は、このドキュメントへの追記、またはコミットメッセージへの記載のいずれかとし、画像追加のコミットと同時に残すことを想定しています。

なお、今回このドキュメントの整理にあたって、画像ファイルそのものの差し替え・削除は行っていません。

---

以上を踏まえ、実際のXserverへのデプロイ作業(サブドメイン作成・DB作成・Cron登録)に進む際は、このドキュメントをチェックリストとして参照してください。
