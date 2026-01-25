# 小贷管理后台 Demo · 功能规划（基于 `html/` 模块）

## 1. 背景与目标
- 现有 `html/` 目录拆分 14 个独立功能页面，配合统一的 `assets/app.js`/`app.css` 完成数据演示。
- 目标是输出一份 **开发可用的功能规划**，明确每个页面的业务定位、交互行为、需要的数据/接口以及与其他模块的协同方式，为后端和前端并行开发提供依据。
- 规划内容覆盖运营分析、客户与贷款全生命周期、通知触达、渠道日志、配置集成等所有模块，并说明需要接入的操作（新增、导出、重置、模拟等）。

## 2. 模块映射概览
| 页面 | HTML | 定位 | 输出/操作 |
| --- | --- | --- | --- |
| 模块导航 | `index.html` | 统一入口 | 左侧导航、恢复演示数据按钮 |
| 首页概览 | `dashboard.html` | 实时运营看板 | KPI 卡片、导出、风险/提醒表格 |
| 报表中心 | `reports.html` | 深度分析 | Chart.js 图表、资产质量、分群分析、导出 |
| 员工管理 | `employees.html` | 组织编制 | 搜索、分页、增删改、CSV 导出、恢复演示数据 |
| 客户管理 | `customers.html` | 客户 360/风险 | 搜索/风险筛选、信用分、档案/绑定/删除等操作 |
| 企微客户库 | `wecom.html` | 企微联系人 | 搜索、部门过滤、绑定关系展示、CSV 导出 |
| 放款管理 | `loans.html` | 贷款审批与状态流转 | 状态 Tab、金额过滤、建贷/编辑、状态切换、沟通 |
| 还款管理 | `repayments.html` | 还款计划执行 | 搜索、状态筛选、标记已还、渠道提醒、导出 |
| 逾期管理 | `overdue.html` | 催收作业台 | 逾期列表、短信/企微提醒、批量催收 |
| 三方扣款记录 | `autopay-logs.html` | 代扣排障 | 搜索、状态过滤、查看批次 |
| 通知中心 | `notifications.html` | 任务编排 | KPI、任务过滤、建单、模拟执行、导出、模板库 |
| 短信发送记录 | `sms-logs.html` | 渠道回执 | 搜索、分页、日志展示 |
| 企微通知记录 | `wecom-logs.html` | 渠道回执 | 搜索、分页、日志展示 |
| 三方配置 | `integrations.html` | 渠道参数 | 企微/短信/代扣表单、保存、测试/模拟 |
| 系统配置 | `settings.html` | 临期策略 | 阈值 & 频率设置、实时预览、恢复默认 |

## 3. 功能模块明细

### 3.1 导航与基础
#### `index.html`（模块导航）
- **定位**：演示入口，提供统一导航菜单与「恢复演示数据」全局操作。
- **数据/状态**：无额外数据请求，点击导航直接跳转对应 HTML。
- **开发要点**：
  - 提供 hook 供业务菜单统一增/删；
  - 若未来接入路由框架，可将导航抽成组件供各页面复用。

### 3.2 运营分析
#### `dashboard.html`（首页概览）
- **目标**：让运营/管理层一屏掌握 KPI、风险客户以及提醒执行情况。
- **关键数据**：
  - `DB.repayments` 聚合待收款、应收/实收、逾期金额；
  - `creditAll()` 计算客户信用分与风险分布；
  - 配置项 `configs.reminder`、`configs.sms`、`configs.autopay`；
  - 渠道日志统计（短信/企微/代扣近 7 日）。
- **交互**：
  - Hero 卡片展示实时日期、核心 KPI，并提供跳转按钮至通知中心/逾期详情；
  - KPI 网格包含 10+ 指标，需支持实时刷新；
  - `导出Excel` 调用 `exportDashboardExcel()`；
  - “高风险 TOP5”“提醒渠道统计”复用 `reports` 列表。
- **开发关注点**：需实现统一的 Dashboard API，将 `snapshot` 中的 `metrics/reminder/assetQuality` 等汇总为接口响应；导出功能需生成 Excel 文件。

#### `reports.html`（报表中心）
- **目标**：向运营/风控输送趋势分析、资产质量、分群表现。
- **关键组件**：
  - Chart.js 图表：现金流、风险分布、净借还、提醒渠道、热力图、雷达图；
  - 表格：资产质量（PAR/NPL/Roll）、队列 Cohort、Vintage、迁移矩阵；
  - 逾期漏斗、风险 TOP5、提醒统计。
- **交互**：
  - 导出 Excel/PDF；
  - 所有图/表需支持加载状态（“加载中…”）。
- **开发实现**：
  - 设计 `/api/reports/summary`, `/api/reports/cohort`, `/api/reports/asset-quality` 等接口；
  - 导出 PDF 建议服务端生成，Excel 可重用 Dashboard 导出逻辑。

### 3.3 人员与客户
#### `employees.html`
- **功能**：员工台账（客户经理、风控等）。
- **交互**：
  - 搜索框（姓名/手机/角色）实时过滤，分页控件共用 `renderPagerControls`;
  - `+ 新增员工` 弹窗支持增改；`删除` 直接移除；
  - `导出CSV` 与 `恢复演示数据`。
- **数据结构**：`{id,name,phone,role,status}`，status 在职/试用/离职。
- **接口建议**：`GET/POST/PUT/DELETE /api/employees` + `GET /api/employees/export`.

#### `customers.html`
- **功能**：客户清单 + 风险层级管理。
- **关键列**：姓名、身份证、电话、归宿地、抵押物条数、企微绑定、信用分、风险等级、在贷笔数/历史、金额统计、最近还款、完结日期、逾期次数。
- **交互**：
  - 搜索 + 风险下拉（低/中/高/极高）；
  - 操作按钮：编辑客户、查看档案、绑定/解绑企微、删除；
  - `openCustomerModal` 内置抵押物维护与“一键建贷”；
  - `openCustomerProfile` 展示信用评估、抵押物、放款记录、沟通记录。
- **信用逻辑**：`computeCustomerCredit` 依据逾期/未结清占比算分->等级。
- **开发要点**：
  - 客户档案接口需返回 `comms`, `collaterals`, `loans`；
  - 一键建贷需传递抵押物 ID/估算额度至放款页面；
  - 删除客户需级联删除贷款及还款计划。

#### `wecom.html`（企微客户库）
- **功能**：展示企业微信同步的客户联系人库，提供团队维度筛选。
- **数据**：`{id,name,dept,wechat,mobile}`，并列出绑定借款人。
- **交互**：搜索、部门下拉、分页、导出 CSV。
- **接口建议**：`GET /api/wecom/contacts?dept=&keywords=`；导出 `GET /api/wecom/contacts/export`.

### 3.4 贷款与贷后
#### `loans.html`
- **功能**：贷款申请/审批/放款全流程。
- **交互**：
  - 搜索 + 金额区间过滤；
  - 状态 Tab（全部/新增/拒绝/放款/完结/逾期），`loanDerivedStatus` 根据还款计划调整；
  - 操作列：编辑、沟通记录、状态流转（新增→放款/拒绝、放款→完结、逾期→完结）、删除；
  - `openLoanModal` 包含客户选择、金额/期数/利率、抵押物勾选、试算、计划预览；
  - `openLoanComms`/`addLoanComm` 维护贷款层的沟通历史。
- **数据要求**：
  - `DB.loans`: `{id,customerId,amount,months,rateMonth,startDate,status,collateralIds,note}`；
  - 保存为“放款”时生成 `repayments`；“完结”标记未还为已还；“拒绝/新增”删除计划。
- **接口建议**：`POST /api/loans` 创建时同步生成 schedule；`PATCH /api/loans/{id}/status`; `GET /api/loans?status=&keywords=&amountMin=&amountMax=`.

#### `repayments.html`
- **功能**：管理所有还款计划。
- **交互**：
  - 搜索客户/贷款号/期次；状态筛选（全部、未还、已还、逾期）；
  - 操作：`标记已还`（手动/三方）、`短信提醒`、`企微提醒`（绑定客户）、`备注`；
  - 列表展示应还/本金/利息、渠道、操作时间。
- **渠道联动**：
  - `smsOne` 写入 `smsLogs`；
  - `wecomPing` 写入 `wecomLogs` 并校验企微绑定；
  - `markPaid` 记录 `payType=autopay/manual`。
- **接口建议**：`PATCH /api/repayments/{id}:mark-paid`, `/notify/sms`, `/notify/wecom`.

#### `overdue.html`
- **功能**：逾期案件列表（按天数降序）。
- **交互**：展示客户+电话、贷款/期次、到期日、逾期天数、应还金额；操作同 `repayments`，新增 `批量模拟短信`（`bulkSMS`）。
- **数据**：过滤 `repayments` 中 `!paid && dueDate < now`，分页。
- **接口建议**：`GET /api/overdue?dpdMin=&dpdMax=`；批量提醒 `POST /api/overdue/remind`.

#### `autopay-logs.html`
- **功能**：三方代扣执行记录。
- **列**：时间、客户、通道、贷款号/期次、金额、状态（成功/重试/失败）、说明。
- **交互**：搜索、状态下拉过滤、分页。
- **数据来源**：`DB.autopayLogs` 由 `generateAutopayLogs` 生成；真实环境需对接扣款回执。
- **接口建议**：`GET /api/autopay/logs?status=&keywords=`。

### 3.5 触达任务与渠道日志
#### `notifications.html`
- **功能**：统一通知任务编排与模板管理。
- **结构**：
  - KPI 卡片：任务总数、运行中、成功率、今日排程；
  - 过滤框：渠道、状态、关键词；
  - 任务表：任务信息、进度条、状态标签、模板、操作（详情/重试/暂停）。
  - `schedule_panel` 显示未来排程；`retry_panel` 显示失败监控；
  - 模板库区块：搜索、导出、添加模板。
- **操作**：
  - `openNotificationModal` 新建/编辑任务（字段：channel/segment/scheduleType/datetime/template/target/owner/priority/notes）；
  - `simulateNotificationRun`、`triggerNotificationRetry`、`toggleNotificationStatus`；
  - `downloadNotificationsCSV` 导出任务；模板支持编辑、复制、测试、导出。
- **数据结构**：
  - 任务：`{id,name,channel,segment,scheduleType,scheduledAt,status,targetCount,sentCount,successCount,failCount,retryCount,templateId,owner,priority,nextRetry,lastError,timeline[],notes}`；
  - 模板：`{id,name,channel,category,variables[],content,retry{max,gap},lastUsed}`。
- **接口建议**：
  - 任务：`GET/POST/PATCH /api/notification-tasks`、`POST /api/notification-tasks/{id}/retry`、`POST /api/notification-tasks/{id}/simulate`；
  - 模板：`GET/POST/PATCH /api/message-templates`、`POST /api/message-templates/{id}/test`、`GET /api/message-templates/export`.

#### `sms-logs.html` & `wecom-logs.html`
- **功能**：短信/企微渠道回执明细。
- **列**：
  - 短信：时间、客户、手机、贷款号/期次、应还金额、内容；
  - 企微：时间、客户、企微联系人、账号/手机号、贷款号/期次、内容。
- **交互**：搜索（客户/手机号/贷款号/消息内容），分页。
- **接口建议**：`GET /api/logs/sms`、`GET /api/logs/wecom`，支持 `keywords`、`start/end`；导出功能可复用 CSV。

### 3.6 集成与配置
#### `integrations.html`
- **表单区**（三列）：
  1. 企业微信：`CorpID/Secret/AgentId`，操作按钮“保存”“模拟同步客户”；
  2. 短信网关：服务商（Aliyun/Tencent/Submail）、Access Key/Secret、签名、模板 ID，“保存”“模拟发送短信”；
  3. 三方代扣：通道（UnionPay/BestPay/MockPay）、商户号、AppKey、回调地址，“保存”“模拟代扣”。
- **数据结构**：`configs.wecom/sms/autopay`。
- **接口建议**：`PUT /api/configs/wecom|sms|autopay` + `POST /api/configs/wecom/sync`、`/sms/mock-send`、`/autopay/mock-deduct`.

#### `settings.html`
- **功能**：维护临期提醒策略。
- **字段**：临期天数阈值（1-30）、提醒频率（1-10 次/天）。
- **交互**：实时预览（`#cfg_summary/#cfg_example`），保存/恢复默认。
- **接口建议**：`GET/PUT /api/configs/reminder`，并提供预览接口 `GET /api/configs/reminder/preview?days=&frequency=`.

### 3.7 企微客户库 & 通知联动
- 企微客户绑定由 `openWecomBindModal` 完成：选择 `wecomContacts` 中的目标记录写入 `customer.wecomId`。
- `wecom.html` 与 `wecom-logs.html`、`notifications.html` 模板协同，确保后端维持 `customer.wecomId` 与 `contactId` 的外键关系。
- `bulkSMS`、`smsOne`、`wecomPing` 等操作写入日志表，供 Dashboard/Reports 汇总。

## 4. 数据与接口定义建议

| 数据对象 | 关键字段 | 备注 |
| --- | --- | --- |
| `Employee` | `id,name,phone,role,status` | 状态用于标记在职/试用/离职 |
| `Customer` | `id,name,idcard,phone,addr,attr,riskLevel,wecomId,collaterals[],comms[]` | `riskLevel` 由后台计算返回；`collaterals` 包含 `id,name,type,discount,pledgeValue` |
| `Loan` | `id,customerId,amount,months,rateMonth,startDate,status,collateralIds[],note,comms[]` | `status` = 新增/拒绝/放款/完结/逾期；`comms` 记录贷款沟通 |
| `Repayment` | `id,loanId,period,dueDate,amount,principal,interest,paid,payDate,payType,remark` | 逾期判断 `!paid && dueDate < now` |
| `OverdueCase`（派生） | `scheduleId,loanId,customerId,dpd,amount` | 可按需落库 |
| `AutopayLog` | `id,time,customerId,customerName,channel,loanId,period,amount,status,message,batch,attempt` | 状态：成功/重试/失败 |
| `NotificationTask` | 见 3.5 | 用于通知中心 |
| `MessageTemplate` | 见 3.5 | 供模板库调用 |
| `SmsLog`/`WecomLog` | `id,time,customerId,customerName,phone/wechat,loanId,period,amount,message` | Dashboard/Reports 引用 |
| `Config` | `wecom,sms,autopay,reminder` | 结构与 integrations/settings 对齐 |

## 5. 开发节奏建议
1. **阶段 1**：抽象公共 API（客户/贷款/计划/配置），补齐增删改查、导出端点；实现 Dashboard/Reports 所需的聚合查询。
2. **阶段 2**：完善通知中心、短信/企微日志与模板配置，接入渠道模拟接口；实现逾期批量提醒、企微绑定逻辑。
3. **阶段 3**：打通三方扣款、真实渠道回执与 re-try 机制；补充权限、审计与导出权限管控。

> 此规划基于 `html/` 下现有页面与 `assets/app.js` 逻辑整理，后续如新增页面/模块，可参照此文档结构追加章节。
