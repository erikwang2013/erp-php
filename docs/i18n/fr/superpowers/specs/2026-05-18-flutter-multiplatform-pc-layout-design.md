# Conception de mise en page de style PC multiplateforme Flutter — spécification de conception

Date : 2026-05-18

## Objectif

Activer les plateformes de bureau macOS et Windows, garantir que toutes les plateformes iOS (iPhone + iPad), macOS, Windows et Linux utilisent une mise en page de style console d'administration PC (barre latérale + barre supérieure + zone de contenu), avec un menu tiroir adapté sur mobile.

## Stratégie de plateforme

| Plateforme | Statut | Description |
|------|------|------|
| Linux | Déjà activé | Aucune opération nécessaire |
| macOS | À activer | `flutter config --enable-macos-desktop` |
| Windows | À activer | `flutter config --enable-windows-desktop` |
| iOS | Déjà présent | Couvre à la fois iPhone (mise en page mobile) et iPad (mise en page bureau) |
| Web | Déjà présent | Aucune opération nécessaire |

L'iPad n'a pas de cible de plateforme dédiée ; il obtient la mise en page bureau via le point de rupture responsive TABLET.

## Points de rupture responsives

| Point de rupture | Plage | Mode de mise en page |
|------|------|----------|
| PHONE | 0 - 767 | Menu tiroir (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barre latérale repliable (repliée par défaut à 64 px) |
| DESKTOP | 1200 - 2460 | Barre latérale (dépliée par défaut à 240 px) |

La largeur minimale de l'iPad en portrait est de 768 px, ce qui correspond à TABLET et lui donne la mise en page à barre latérale.
Les largeurs d'iPhone sont toutes inférieures à 768 px, ce qui correspond à PHONE et lui donne le menu tiroir.

## Modifications de fichiers

### 1. main.dart — configuration des points de rupture

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Reste du code inchangé

### 2. admin_layout.dart — bascule de navigation responsive

- `_isPhone`: correspond au point de rupture PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, le NavigationDrawer du Drawer réutilise les mêmes éléments de menu que la barre latérale bureau
- `_buildDesktopLayout()`: mise en page Row existante (barre latérale + barre supérieure + zone de contenu)
- En TABLET la barre latérale est repliée par défaut, en DESKTOP elle est dépliée par défaut

### 3. app_theme.dart — complétion du thème sombre

- Extraire les styles de composants en constantes privées `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Les thèmes clair et sombre réutilisent le même ensemble de styles de composants
- Le thème sombre utilise Material 3 + le même seed + la luminosité dark
