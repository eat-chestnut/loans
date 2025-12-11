# Laravel Backend – 项目规则
Activation Mode: Always On

## 项目背景
- 这是一个 Laravel 后台项目，主要目录结构遵循：
  - app/Models
  - app/Services
  - app/Http/Controllers
  - app/Http/Requests
  - database/migrations, database/seeders

## 代码风格
- 使用 PHP 8.2+，在新文件中尽量加上 `declare(strict_types=1);`
- 遵守 PSR-12 代码规范。
- 所有 PHP 代码必须显式声明返回类型、参数类型。
- 使用 Laravel Pint 或 PHP-CS-Fixer 自动格式化（按仓库里的配置执行）。

## 业务分层约定
- Controller 只做：参数获取、调用 Service、返回响应（不写复杂业务逻辑）。
- 所有业务逻辑写在 `app/Services` 中的 Service 类，命名规则：`SomethingService`。
- 校验逻辑放在 `app/Http/Requests` 的 FormRequest 里，Controller 中通过依赖注入使用。
- 数据访问优先使用 Eloquent 关系；只有在确实需要时才写原生 SQL 或 Query Builder。

## 命名与约定
- Eloquent 模型命名使用单数大驼峰，例如：`MarketingCampaign`, `MarketingCreditWallet`。
- 数据表命名使用 snake_case 复数，例如：`marketing_campaigns`, `marketing_credit_wallets`。
- 带有软删除的模型必须使用 `SoftDeletes` trait，并在迁移中添加 `softDeletes()`。

## 开发建议
- 当你生成新代码时：
  - 优先查看已有的 Model、Service、Controller 并保持风格一致。
  - 如果需要新增接口：生成 Route → Controller → Service → Request → Resource。
- 编写示例代码时，尽量使用项目中已有的工具方法、基类与 Trait，而不是重新造轮子。
