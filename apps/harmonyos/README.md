# open-erp 鸿蒙客户端 (HarmonyOS)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

开放ERP系统（webman v2 + Flutter 全栈 ERP）的鸿蒙原生客户端，基于 ArkTS 开发，支持手机/平板/2in1。

## 运行

1. 启动后端服务（见[主 README 快速开始](../../README.md#快速开始)）
2. 使用 DevEco Studio 打开本目录（`apps/harmonyos/`）
3. 连接真机或启动模拟器，运行到设备

后端地址在 `entry/src/main/ets/service/ApiService.ets` 的 `BASE_URL` 常量配置（默认 `http://10.0.2.2:8788` 模拟器访问宿主机；真机改为 `http://<服务器IP>:8788`）。

## 页面

- 登录、仪表盘、个人中心、用户列表/详情
- 商品、销售订单、采购订单、库存
- 审批工作流、人事（部门/员工/考勤）
- 生产制造：BOM/生产订单/工艺路线/工作站/MRP
- OMS：订单/履约/渠道/RMA
- WMS：库区/ASN/收货/上架/波次/拣货/打包
- TMS：承运商/费率/运单/轨迹/运费发票

## 认证

JWT Bearer + 401 自动无感刷新 Token，刷新失败自动重定向登录页；Token 通过 AppStorage 管理。

## 相关文档

- [主 README](../../README.md) | [功能手册](../../docs/FUNCTIONS.md) | [API 参考](../../docs/API.md)
