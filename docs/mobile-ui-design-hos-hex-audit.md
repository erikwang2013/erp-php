# HOS 页面 hex → 色彩 token 替换清单（audit 快照 2026-09-06）

> 配套 `docs/mobile-ui-design.md` 8.2.2 与 `docs/mobile-ui-design-ext.md` 附录 A/B。
> 执行时把每个 `#RRGGBB` 字面量替换为对应 `$r('app.color.xxx')`；规则列 = token 归属判定。
> 已登记 15 色全在附录 A/B token 集内，无未登记色；替换后页面内 6 位 hex 应清零。
> 保留例外（非 6 位）：8 位带 alpha 色（阴影/遮罩，如 #00000010、#1677FFCC）与 rgba()。
> 深色由 `resources/dark/element/color.json` 同 key 覆写自动生效，无需页面侧分支。

## 全库色值覆盖（pages 35 文件，共 808 实例，15 色全登记）

| hex | 实例数 | token 规则 |
|---|---|---|
| #FFFFFF | 212 | surface（backgroundColor 底色）；text_on_primary（fontColor/fillColor 位于主色/语义实心按钮上的白字） |
| #EEEEEE | 123 | divider |
| #333333 | 110 | text_primary |
| #1677FF | 67 | primary |
| #F5F5F5 | 55 | bg_page（页面底）；surface_alt（禁用按钮底） |
| #FF4D4F | 53 | danger（实心底/图标/状态圆点）；danger_text（fontColor 于浅底/行内删除文字） |
| #666666 | 36 | text_secondary |
| #FFE6E6 | 25 | danger_bg |
| #999999 | 29 | text_hint |
| #52C41A | 24 | success（实心/图标/圆点）；success_text（fontColor 于浅底文字） |
| #E6F0FF | 18 | primary_bg |
| #CCCCCC | 18 | text_disabled |
| #99BBFF | 14 | primary_disabled |
| #F0F0F0 | 12 | surface_alt |
| #E6FFF0 | 12 | success_bg |

替换完成标准：`rg -i '#[0-9a-f]{6}' pages` 无输出；`common/` 仅剩数据默认值与 8 位 alpha。

## 逐页清单（hex × 属性分布 × 应换 token）

== 全库 distinct ==
 1677FF x67 登记
 333333 x110 登记
 52C41A x24 登记
 666666 x36 登记
 999999 x29 登记
 99BBFF x14 登记
 CCCCCC x18 登记
 E6F0FF x18 登记
 E6FFF0 x12 登记
 EEEEEE x123 登记
 F0F0F0 x12 登记
 F5F5F5 x55 登记
 FF4D4F x53 登记
 FFE6E6 x25 登记
 FFFFFF x212 登记

== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/DashboardPage.ets ==
  #1677FF  x2  [fontColor:2]  -> primary
  #333333  x1  [fontColor:1]  -> text_primary
  #999999  x3  [fontColor:3]  -> text_hint
  #E6F0FF  x2  [backgroundColor:1, other:1]  -> primary_bg
  #EEEEEE  x1  [color:1]  -> divider
  #F5F5F5  x1  [backgroundColor:1]  -> bg_page(页面底) | surface_alt(禁用底)
  #FFFFFF  x2  [backgroundColor:2]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/InventoryPage.ets ==
  #1677FF  x1  [backgroundColor:1]  -> primary
  #999999  x1  [fontColor:1]  -> text_hint
  #FFFFFF  x1  [fontColor:1]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/LoginPage.ets ==
  #1677FF  x4  [backgroundColor:1, fontColor:3]  -> primary
  #999999  x2  [fontColor:2]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #F5F5F5  x2  [backgroundColor:2]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x1  [fontColor:1]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFFFFF  x2  [backgroundColor:1, fontColor:1]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/ProductListPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x9  [fontColor:9]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x10  [color:10]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x16  [backgroundColor:12, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/ProfilePage.ets ==
  #333333  x1  [fontColor:1]  -> text_primary
  #999999  x2  [fontColor:2]  -> text_hint
  #CCCCCC  x2  [fillColor:1, fontColor:1]  -> text_disabled
  #E6F0FF  x1  [other:1]  -> primary_bg
  #EEEEEE  x1  [color:1]  -> divider
  #F5F5F5  x1  [backgroundColor:1]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x2  [fontColor:2]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFFFFF  x5  [backgroundColor:5]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/PurchaseOrderPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x9  [fontColor:9]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x10  [color:10]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x16  [backgroundColor:12, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/SalesOrderPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x10  [fontColor:10]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x11  [color:11]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x17  [backgroundColor:13, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/SplashPage.ets ==
  #1677FF  x2  [color:1, fontColor:1]  -> primary
  #999999  x2  [fontColor:2]  -> text_hint
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #FFFFFF  x1  [backgroundColor:1]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/UserDetailPage.ets ==
  #1677FF  x1  [backgroundColor:1]  -> primary
  #333333  x6  [fontColor:6]  -> text_primary
  #52C41A  x1  [backgroundColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x2  [fontColor:2]  -> text_secondary
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #E6F0FF  x1  [other:1]  -> primary_bg
  #EEEEEE  x6  [color:6]  -> divider
  #F5F5F5  x3  [backgroundColor:3]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x3  [backgroundColor:1, fontColor:2]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x1  [backgroundColor:1]  -> danger_bg
  #FFFFFF  x11  [backgroundColor:8, fontColor:3]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/UserListPage.ets ==
  #1677FF  x3  [backgroundColor:2, fontColor:1]  -> primary
  #52C41A  x1  [fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #999999  x1  [fontColor:1]  -> text_hint
  #CCCCCC  x2  [fillColor:1, fontColor:1]  -> text_disabled
  #E6F0FF  x1  [other:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x1  [color:1]  -> divider
  #F5F5F5  x2  [backgroundColor:2]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x1  [fontColor:1]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x1  [backgroundColor:1]  -> danger_bg
  #FFFFFF  x6  [backgroundColor:4, fontColor:2]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/hr/AttendancePage.ets ==
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/hr/DepartmentPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x5  [fontColor:5]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x6  [color:6]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x12  [backgroundColor:8, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/hr/EmployeeListPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x9  [fontColor:9]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x10  [color:10]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x16  [backgroundColor:12, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/mfg/BomListPage.ets ==
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/mfg/MrpPage.ets ==
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/mfg/ProductionOrderPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x8  [fontColor:8]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x9  [color:9]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x15  [backgroundColor:11, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/mfg/RoutingPage.ets ==
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/mfg/WorkstationPage.ets ==
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/oms/ChannelListPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x4  [fontColor:4]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x5  [color:5]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x11  [backgroundColor:7, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/oms/FulfillmentListPage.ets ==
  #1677FF  x1  [fontColor:1]  -> primary
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets ==
  #1677FF  x5  [backgroundColor:2, fontColor:3]  -> primary
  #333333  x13  [fontColor:13]  -> text_primary
  #666666  x1  [fontColor:1]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x2  [backgroundColor:2]  -> primary_bg
  #EEEEEE  x14  [color:14]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x2  [backgroundColor:2]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x2  [fontColor:2]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x1  [backgroundColor:1]  -> danger_bg
  #FFFFFF  x18  [backgroundColor:16, fontColor:2]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/oms/RmaListPage.ets ==
  #1677FF  x1  [fontColor:1]  -> primary
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/tms/CarrierListPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x8  [fontColor:8]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x9  [color:9]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x15  [backgroundColor:11, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/tms/FreightInvoicePage.ets ==
  #1677FF  x1  [fontColor:1]  -> primary
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/tms/FreightRatePage.ets ==
  #1677FF  x1  [fontColor:1]  -> primary
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/tms/ShipmentListPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x12  [fontColor:12]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x13  [color:13]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x19  [backgroundColor:15, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/tms/TrackingPage.ets ==
  #1677FF  x1  [fontColor:1]  -> primary
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/wms/AsnListPage.ets ==
  #999999  x1  [fontColor:1]  -> text_hint
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/wms/PackPage.ets ==
  #999999  x1  [fontColor:1]  -> text_hint
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/wms/PickPage.ets ==
  #999999  x1  [fontColor:1]  -> text_hint
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/wms/PutawayPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x7  [fontColor:7]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x8  [color:8]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x14  [backgroundColor:10, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/wms/ReceivingPage.ets ==
  #1677FF  x4  [backgroundColor:2, fontColor:2]  -> primary
  #333333  x8  [fontColor:8]  -> text_primary
  #52C41A  x2  [backgroundColor:1, fontColor:1]  -> success(实心/图标/圆点) | success_text(fontColor 于浅底文字)
  #666666  x3  [fontColor:3]  -> text_secondary
  #999999  x1  [fontColor:1]  -> text_hint
  #99BBFF  x1  [backgroundColor:1]  -> primary_disabled
  #CCCCCC  x1  [fontColor:1]  -> text_disabled
  #E6F0FF  x1  [backgroundColor:1]  -> primary_bg
  #E6FFF0  x1  [backgroundColor:1]  -> success_bg
  #EEEEEE  x9  [color:9]  -> divider
  #F0F0F0  x1  [backgroundColor:1]  -> surface_alt
  #F5F5F5  x4  [backgroundColor:4]  -> bg_page(页面底) | surface_alt(禁用底)
  #FF4D4F  x4  [backgroundColor:1, fontColor:3]  -> danger(实心/图标/圆点) | danger_text(fontColor 于浅底/行内删除文字)
  #FFE6E6  x2  [backgroundColor:2]  -> danger_bg
  #FFFFFF  x15  [backgroundColor:11, fontColor:4]  -> surface(底色/填充) | text_on_primary(fontColor/fillColor 于主/语义实心按钮)
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/wms/WavePage.ets ==
  #999999  x1  [fontColor:1]  -> text_hint
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/wms/ZoneListPage.ets ==
  #999999  x1  [fontColor:1]  -> text_hint
== /home/wwwroot/erp-php/apps/harmonyos/entry/src/main/ets/pages/workflow/ApprovalPage.ets ==
  #999999  x1  [fontColor:1]  -> text_hint
