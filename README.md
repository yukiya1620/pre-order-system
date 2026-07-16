# 予約注文システム(Pre-order System)

「誰でも迷わず使える。農家のためのやさしい予約注文システム」をコンセプトにした、野菜・果物の予約注文・販売管理システムです。

## コンセプト

- 高齢者にも使いやすいことを最優先にしています(大きな文字・大きなボタン・画面遷移型のシンプルなUI)。
- 決済はオフライン(現金・代引き等)が前提です。オンライン決済は将来の拡張として扱います。

## 利用者とロール

- **農家(販売者・管理者)**: 商品管理・注文管理・配達確認・売上確認・お知らせ投稿・電話注文の代理入力を行います。1農家 = 1システム(シングルテナント)の運用を前提としています。
- **購入者(一般ユーザー)**: 商品の閲覧・予約注文・注文履歴の確認・通知の確認を行います。

## 主な機能

- 商品マスタと販売シーズンの分離管理(翌年は前年の商品を選んで価格・在庫だけ入力すれば再販売できます)
- 在庫の排他制御を伴う予約注文
- 配達予定日の3日前確認(固定配達日)、当日・翌日配達の即時確認(自動計算配達日)
- 農家によるF4画面からの注文の数量減少・キャンセル(購入者への確認後に確定)
- 売上確認(確定売上・予定売上、支払い状況別・支払い方法別の内訳、商品別売上)
- お知らせ投稿・通知
- 電話注文の代理入力(農家が購入者に代わって注文を入力)

画面・API・データベースの詳細は [docs/設計書.md](docs/設計書.md) を参照してください。

## 使用技術・必要環境

- PHP 8.2以上(開発時はPHP 8.3.6で動作確認)
- [Composer](https://getcomposer.org/)
- Laravel 11
- Laravel Sanctum(認証。購入者はSMS認証、農家はメール+パスワード)
- DB: SQLite(開発・デモ環境。`database/database.sqlite`)

このアプリの画面(Blade)は `public/css/app.css`・`public/js/*.js` を直接読み込む方式で、ビルドツール(Vite)を使用していません。`package.json` 等のフロントエンド関連ファイルはリポジトリに残っていますが、**`npm install` や `npm run build` は不要**です。

## 初回セットアップ

```bash
composer install
```

### .envファイルの作成とAPP_KEYの生成

**macOS / Linux**

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
```

**Windows (PowerShell)**

```powershell
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database/database.sqlite -Force
```

`.env` の `DB_DATABASE` は未指定のままでよく、その場合 `database/database.sqlite` が自動的に使われます(`config/database.php` のデフォルト設定)。

### マイグレーション・初期データ・画像アップロード用リンク

```bash
php artisan migrate --seed
php artisan storage:link
```

`storage:link` を実行し忘れると、商品画像のURLが正しく解決できず404になります(F5/F6画面で画像アップロード機能を使う場合に必要)。

### 書き込み権限について

以下のディレクトリ・ファイルには実行ユーザーの書き込み権限が必要です。

- `storage/`
- `bootstrap/cache/`
- `database/database.sqlite`

## 起動方法・アクセス先

```bash
php artisan serve
```

起動後は `http://127.0.0.1:8000` にアクセスします。

- 購入者トップ: `http://127.0.0.1:8000/`
- 購入者会員登録: `http://127.0.0.1:8000/register`
- 農家ログイン: `http://127.0.0.1:8000/login`

## デモ用農家アカウント

`php artisan migrate --seed` を実行すると、動作確認用の農家アカウントが1件作成されます。

- メールアドレス: `farmer@example.com`
- パスワード: `password`

**このアカウントはローカル・デモ環境専用です。本番環境で使う場合は、必ずパスワードを変更するか、アカウント自体を削除してください。**

購入者の初期アカウントは存在しません。購入者は会員登録画面(`/register`)からSMS認証で自分自身のアカウントを作成します(下記「購入者のSMS認証登録」を参照)。

## 購入者のSMS認証登録

会員登録画面(`/register`)から、名前・電話番号・住所・メール(任意)を入力し、SMS認証コードを検証してアカウントを作成します。開発・デモ環境でのSMS認証コードの確認方法は「SMS送信の現在の仕様」を参照してください。

## テスト実行方法

```bash
php artisan test
```

`phpunit.xml` でテスト実行時のみインメモリSQLite(`DB_DATABASE=:memory:`)を使うよう設定されているため、開発用の `database/database.sqlite` には影響しません。

## タイムゾーン

`.env` の `APP_TIMEZONE` は `Asia/Tokyo` に設定します。売上集計の「今日・今月・今年」、配達予定日の自動計算、バッチ処理の実行時刻はすべて日本時間基準で扱われます。

## スケジューラ

以下のバッチ処理が `routes/console.php` に登録されています。

| コマンド | 実行時刻 | 内容 |
|---|---|---|
| `orders:generate-delivery-confirmations` | 毎日 07:00 | 配達予定日が3日後の受付済の注文に配達確認を作成し、農家へ通知する |
| `orders:update-product-sale-statuses` | 毎日 00:05 | 販売シーズンの状態(準備中/販売中/販売終了)を日付に基づいて更新する |
| `orders:remind-unanswered-delivery-confirmations` | 毎日 17:00 | 未回答の配達確認について、農家へ再通知する |

**開発環境**では、以下のコマンドを起動しておくとスケジュールが1分ごとにチェックされます。

```bash
php artisan schedule:work
```

**本番環境**では、crontabに以下の1行を登録します(`/path/to/pre-order-system` は実際のデプロイ先のディレクトリに置き換えてください)。

```
* * * * * cd /path/to/pre-order-system && php artisan schedule:run >> /dev/null 2>&1
```

## SMS送信の現在の仕様

**現状、SMSは実際には送信されません。** `App\Services\Sms\LogSmsSender` が `SmsSender` の実装として使われており、送信内容(電話番号・認証コード)は実際のSMS APIを呼ばず、ログファイル(`storage/logs/laravel.log`)に書き出されるだけです。開発・デモ環境で認証コードを確認する場合は、このログファイルを開いてください。

**注意**: このログには電話番号と認証コードが平文で含まれます。外部に公開したり、無関係な人がアクセスできる状態にしたりしないよう、適切に管理してください。

本番環境でSMSを実際に送信するには、Twilio等の実際のSMS送信APIを呼び出す `SmsSender` の実装を新たに作成し、`App\Providers\AppServiceProvider` のバインド先を差し替える必要があります(現時点ではこの本番実装は未着手です)。

## 本番環境での注意事項

- `.env` の `APP_ENV` を `production`、`APP_DEBUG` を `false` に設定してください(`.env.example` は開発向けの値のままです)。
- `APP_KEY` や `.env` ファイル自体、`database/database.sqlite` をGitやその他の方法で公開しないでください。
- 上記の「デモ用農家アカウント」のパスワードを変更するか、アカウントを削除してください。
- 上記の「SMS送信の現在の仕様」の通り、本番でSMS認証を使う場合は送信実装の差し替えが必須です。
- 必要に応じて、以下のような最適化コマンドを実行してください(任意のデプロイ手順の一例です)。

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

これらのキャッシュを作成した状態で `.env` やルート定義を変更した場合は、`php artisan optimize:clear` でキャッシュを解除してから反映し直してください。

## 設計書

画面・API・データベース設計の詳細は [docs/設計書.md](docs/設計書.md) を参照してください。

## 既知の制約

- 本番向けのSMS送信サービス(Twilio等)は未実装です(上記「SMS送信の現在の仕様」を参照)。
- 商品画像を差し替えると、古い画像ファイルが `storage` に残り続ける場合があります(削除処理が未実装)。
- 1農家 = 1システムのシングルテナント運用を前提とした設計です。複数の農家が同一システムを共用する運用には対応していません。
