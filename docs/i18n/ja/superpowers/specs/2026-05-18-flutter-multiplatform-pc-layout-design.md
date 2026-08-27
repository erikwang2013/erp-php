# Flutter マルチプラットフォーム PC スタイルレイアウト — 設計仕様

日付: 2026-05-18

## 目標

macOS、Windows デスクトッププラットフォームを有効化し、iOS (iPhone + iPad)、macOS、Windows、Linux のすべてのプラットフォームで PC 管理バックエンドスタイルのレイアウト（サイドバー + ヘッダー + コンテンツエリア）を使用し、モバイルではドロワーメニューを採用して適応させる。

## プラットフォーム戦略

| プラットフォーム | 状態 | 説明 |
|------|------|------|
| Linux | 有効済み | 操作不要 |
| macOS | 有効化が必要 | `flutter config --enable-macos-desktop` |
| Windows | 有効化が必要 | `flutter config --enable-windows-desktop` |
| iOS | 既存 | iPhone (モバイルレイアウト) と iPad (デスクトップレイアウト) の両方をカバー |
| Web | 既存 | 操作不要 |

iPad には独立したプラットフォームターゲットがなく、レスポンシブブレークポイントで TABLET に該当させデスクトップレイアウトを実現する。

## レスポンシブブレークポイント

| ブレークポイント | 範囲 | レイアウトモード |
|------|------|----------|
| PHONE | 0 - 767 | ドロワーメニュー (AppBar + Drawer) |
| TABLET | 768 - 1199 | 折りたたみ可能なサイドバー (デフォルト折りたたみ 64px) |
| DESKTOP | 1200 - 2460 | サイドバー (デフォルト展開 240px) |

iPad 縦向きの最小幅は 768px で TABLET に該当し、サイドバーレイアウトになる。
iPhone の幅はすべて 768px 未満で PHONE に該当し、ドロワーメニューになる。

## ファイル変更

### 1. main.dart — ブレークポイント設定

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- その他のコードは変更なし

### 2. admin_layout.dart — レスポンシブナビゲーション切替

- `_isPhone`: PHONE ブレークポイントに該当
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer。Drawer 内の NavigationDrawer はデスクトップサイドバーと同じメニュー項目を再利用
- `_buildDesktopLayout()`: 既存の Row レイアウト（サイドバー + ヘッダー + コンテンツエリア）
- TABLET ではサイドバーがデフォルト折りたたみ、DESKTOP ではデフォルト展開

### 3. app_theme.dart — ダークテーマの補完

- コンポーネントスタイルをプライベート定数 `_dataTableTheme`、`_cardTheme`、`_inputDecorationTheme`、`_dividerTheme` に抽出
- ライトテーマとダークテーマで同じコンポーネントスタイルを再利用
- ダークテーマには Material 3 + 同じ seed + dark 明度を追加
