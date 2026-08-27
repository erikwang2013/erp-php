# Layout multiplataforma estilo PC do Flutter — Especificação de design

Data: 2026-05-18

## Objetivo

Habilitar as plataformas desktop macOS e Windows, garantindo que todas as plataformas — iOS (iPhone + iPad), macOS, Windows, Linux — usem o layout estilo painel de administração para PC (barra lateral + barra superior + área de conteúdo), com menu gaveta no celular.

## Estratégia de plataformas

| Plataforma | Status | Observação |
|------|------|------|
| Linux | Habilitada | Sem ação necessária |
| macOS | Precisa habilitar | `flutter config --enable-macos-desktop` |
| Windows | Precisa habilitar | `flutter config --enable-windows-desktop` |
| iOS | Já existe | Cobre iPhone (layout de celular) e iPad (layout de desktop) |
| Web | Já existe | Sem ação necessária |

O iPad não tem alvo de plataforma independente; alcança o layout de desktop pelo breakpoint responsivo que aciona a faixa TABLET.

## Breakpoints responsivos

| Breakpoint | Faixa | Modo de layout |
|------|------|----------|
| PHONE | 0 - 767 | Menu gaveta (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barra lateral recolhível (recolhida por padrão em 64px) |
| DESKTOP | 1200 - 2460 | Barra lateral (expandida por padrão em 240px) |

A largura mínima do iPad em retrato é 768px, que aciona TABLET e obtém o layout com barra lateral.
As larguras do iPhone são todas menores que 768px, acionando PHONE e obtendo o menu gaveta.

## Alterações de arquivos

### 1. main.dart — Configuração de breakpoints

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- O restante do código permanece inalterado

### 2. admin_layout.dart — Alternância de navegação responsiva

- `_isPhone`: aciona o breakpoint PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; o NavigationDrawer dentro do Drawer reutiliza os mesmos itens de menu da barra lateral do desktop
- `_buildDesktopLayout()`: layout Row existente (barra lateral + barra superior + área de conteúdo)
- Em TABLET a barra lateral fica recolhida por padrão; em DESKTOP fica expandida por padrão

### 3. app_theme.dart — Complemento do tema escuro

- Extrair os estilos de componentes para constantes privadas `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Os temas claro e escuro reutilizam o mesmo conjunto de estilos de componentes
- O tema escuro usa Material 3 + mesmo seed + luminosidade dark
