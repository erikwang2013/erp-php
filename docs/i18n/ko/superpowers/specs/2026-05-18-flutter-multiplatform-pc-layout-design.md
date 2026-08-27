# Flutter 멀티플랫폼 PC 스타일 레이아웃 — 설계 규격

날짜: 2026-05-18

## 목표

macOS, Windows 데스크톱 플랫폼을 활성화하고, iOS (iPhone + iPad), macOS, Windows, Linux 모든 플랫폼이 PC 관리 백오피스 스타일 레이아웃(사이드바 + 탑 바 + 콘텐츠 영역)을 사용하도록 보장하며, 모바일은 드로어 메뉴로 적응합니다.

## 플랫폼 전략

| 플랫폼 | 상태 | 설명 |
|------|------|------|
| Linux | 활성화됨 | 조치 불필요 |
| macOS | 활성화 필요 | `flutter config --enable-macos-desktop` |
| Windows | 활성화 필요 | `flutter config --enable-windows-desktop` |
| iOS | 이미 존재 | iPhone(모바일 레이아웃)과 iPad(데스크톱 레이아웃) 모두 커버 |
| Web | 이미 존재 | 조치 불필요 |

iPad는 별도 플랫폼 타깃이 없으며, 반응형 브레이크포인트가 TABLET 단계를 매칭하여 데스크톱 레이아웃을 구현합니다.

## 반응형 브레이크포인트

| 브레이크포인트 | 범위 | 레이아웃 모드 |
|------|------|----------|
| PHONE | 0 - 767 | 드로어 메뉴(AppBar + Drawer) |
| TABLET | 768 - 1199 | 접이식 사이드바(기본 접힘 64px) |
| DESKTOP | 1200 - 2460 | 사이드바(기본 펼침 240px) |

iPad 세로 최소 너비 768px로 TABLET에 매칭되어 사이드바 레이아웃을 얻습니다.
iPhone 너비는 모두 768px 미만으로 PHONE에 매칭되어 드로어 메뉴를 얻습니다.

## 파일 변경

### 1. main.dart — 브레이크포인트 설정

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- 나머지 코드 변경 없음

### 2. admin_layout.dart — 반응형 내비게이션 전환

- `_isPhone`: PHONE 브레이크포인트 매칭
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, Drawer 내 NavigationDrawer가 데스크톱 사이드바와 동일한 메뉴 항목을 재사용
- `_buildDesktopLayout()`: 기존 Row 레이아웃(사이드바 + 탑 바 + 콘텐츠 영역)
- TABLET에서는 사이드바 기본 접힘, DESKTOP에서는 기본 펼침

### 3. app_theme.dart — 다크 테마 보완

- 컴포넌트 스타일을 프라이빗 상수 `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`로 추출
- 라이트/다크 테마가 동일한 컴포넌트 스타일 재사용
- 다크 테마는 Material 3 + 동일한 seed + dark 밝기 사용
