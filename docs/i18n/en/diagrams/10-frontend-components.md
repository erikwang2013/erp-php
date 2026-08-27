# Frontend Component Architecture

## Flutter Web Component Tree

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Login form<br/>username+password"]
    login --> captcha["Click captcha component<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Click markers Circle"]

    dashboard --> sidebar["Sidebar NavigationDrawer<br/>Collapsible 64px/240px<br/>Dashboard/Users/Roles/Config/Logs"]
    dashboard --> header["Top bar 56px<br/>Collapse button + user menu<br/>Logout confirm AlertDialog"]
    dashboard --> content["Content area"]

    content --> stats["Stat cards GridView×4"]
    content --> chart["Trend line chart LineChart"]
    content --> pie["Distribution pie chart PieChart"]
    content --> logs["Recent operations ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## HarmonyOS Page Routing

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"No Token"| loginH["LoginPage"]
    entry -->|"Has Token"| dashH["DashboardPage"]

    loginH -->|"Login success replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Logout confirm replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
