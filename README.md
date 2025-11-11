# thread-app-advenced

XAMPP 上で動作するシンプルなスレッド型コミュニケーションアプリ（学習用・応用課題）。

PHP（PDO）/ MySQL / JavaScript（Fetch API）で構成し、ログイン、スレッド作成、返信、編集・削除、プロフィール編集などの基本機能を実装しています。今後はコード整理やライブラリ導入、機能拡張を予定しています。

スクリーンショット:
<img width="743" height="408" alt="Image" src="https://github.com/user-attachments/assets/1c9f8b8a-293d-40e0-95b8-b6badfe19cf8" />

---

## 概要

- 言語・技術: PHP / JavaScript / SQL
- データベース: MySQL
- 実行環境: XAMPP（Apache + MySQL）, VS Code など
- バージョン管理: Git, GitHub

### 主な機能
- ユーザー登録・ログイン / ログアウト
- スレッド一覧表示・新規スレッド作成
- 投稿への返信（階層表示）
- 投稿の編集・削除（本人のみ）
- プロフィール一覧・プロフィール編集（本人のみ）
- ページング・ソート、セッションによるアクセス制御

### 主要ファイル
| ファイル | 役割 |
|---------|------|
| `login.php` | ログインページ |
| `logout.php` | ログアウト処理 |
| `register.php` | 新規ユーザー登録 |
| `thread_list.php` | スレッド一覧表示 |
| `new_thread.php` | 新規スレッド投稿フォーム |
| `edit_post.php` | 投稿編集ページ |
| `profile_list.php` | プロフィール一覧表示 |
| `edit_profile.php` | プロフィール編集 |
| `api.php` | 簡易 API エンドポイント |
| `auth.php` | 認証関連の共通処理 |
| `db.php.example` | DB 接続設定のサンプル（実運用は `db.php` を作成） |

### ディレクトリ構成（抜粋）
```
thread_app_advanced/
├── api.php
├── auth.php
├── db.php.example
├── edit_post.php
├── edit_profile.php
├── login.php
├── logout.php
├── new_thread.php
├── profile_list.php
├── register.php
├── thread_list.php
├── style_*.css
└── README.md
```

---

## セットアップ

注意: GitHub のリポジトリ名は `thread-app-advenced`、ローカルのフォルダ名は `thread_app_advanced` です。クローン後のディレクトリ名は GitHub のリポジトリ名になります。

### 1) クローン
```bash
git clone https://github.com/Ikken-Sakai/thread-app-advenced.git
cd thread-app-advenced
```

### 2) DB 設定ファイルを用意
`db.php.example` を `db.php` にコピーし、環境に合わせて編集します。
```bash
# Windows (PowerShell)
copy db.php.example db.php

# macOS / Linux
cp db.php.example db.php
```

`db.php` の主な項目:
- `$dsn` 例: `mysql:host=localhost;dbname=chat_app;charset=utf8mb4`
- `$db_user` 例: `root`
- `$db_pass` 例: 空文字（XAMPP 既定）

`db.php` は機密情報を含むためコミットしません（`.gitignore` 済み）。

### 3) データベースを作成
MySQL でデータベースを作成してください（例）。テーブル定義はプロジェクトの実装に合わせて準備してください。
```sql
CREATE DATABASE chat_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chat_app;
-- 必要なテーブルを作成
```

### 4) XAMPP を起動してアクセス
- XAMPP コントロールパネルで Apache と MySQL を起動
- ブラウザで `http://localhost/thread_app_advanced/` を開く

---

## 開発メモ

- コーディング規約やディレクトリ構成は今後整理予定
- ライブラリ導入（フロント・バック）や機能拡張の検討中
- 例: 入力バリデーション強化、CSRF 対策、画像アップロード、検索、通知など

## Git/GitHub 運用（参考）
```bash
# 変更確認
git status

# 変更ステージング
git add -A

# コミット
git commit -m "feat: ..."

# プッシュ
git push origin main
```

---

## ライセンス
学習用プロジェクトのため未定義。必要に応じて追加してください。

