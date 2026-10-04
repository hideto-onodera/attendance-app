# 勤怠管理アプリ

一般ユーザーの勤怠登録・勤怠確認・修正申請と、管理者による勤怠管理を行うWebアプリケーションです。

## 環境構築

### Dockerビルド

1. リポジトリを取得します。

```bash
git clone https://github.com/hideto-onodera/attendance-app.git
```

2. プロジェクトディレクトリへ移動します。

```bash
cd attendance-app
```

3. `.env.example` をコピーして `.env` を作成します。

```bash
cp .env.example .env
```

4. PHPの依存パッケージをインストールします。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

5. Dockerコンテナを起動します。

```bash
./vendor/bin/sail up -d
```

### Laravel環境構築

1. アプリケーションキーを生成します。

```bash
./vendor/bin/sail artisan key:generate
```

2. マイグレーションとシーディングを実行します。

```bash
./vendor/bin/sail artisan migrate --seed
```

3. JavaScriptの依存パッケージをインストールします。

```bash
./vendor/bin/sail npm install
```

4. Viteを起動します。

```bash
./vendor/bin/sail npm run dev
```

## 使用技術

- PHP 8.5
- Laravel 10
- MySQL 8.4
- Laravel Fortify
- Laravel Sail
- Vite 5
- Mailpit
- phpMyAdmin

## ログイン情報

### 一般ユーザー

- メールアドレス: user1@example.com
- パスワード: password

- メールアドレス: user2@example.com
- パスワード: password

### 管理者ユーザー

- メールアドレス: user3@example.com
- パスワード: password

## 主なURL

- 一般ユーザーログイン: http://localhost/login
- 一般ユーザー登録: http://localhost/register
- 勤怠登録: http://localhost/attendance
- 一般ユーザー勤怠一覧: http://localhost/attendance/list
- 修正申請一覧: http://localhost/stamp_correction_request/list
- 管理者ログイン: http://localhost/admin/login
- 管理者勤怠一覧: http://localhost/admin/attendance/list
- スタッフ一覧: http://localhost/admin/staff/list

## 開発用サービス

- アプリケーション: http://localhost
- phpMyAdmin: http://localhost:8080
- Mailpit: http://localhost:8025

## データベース

主なテーブルは以下のとおりです。

- users
- attendance_records
- breaks
- applications
- application_breaks

## ER図

ER図は提出用のテーブル仕様書に記載しています。