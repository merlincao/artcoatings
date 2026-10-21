# artcoatings.org 开发框架（v0.2 · 国际化版）

> **ArtCoatings — Art Coatings Directory** · 面向国际（英语）、定位意大利西西里 · WordPress 本地创作 + 静态导出 + Cloudflare Pages 托管
>
> 变更记录：v0.2 起全站转为英文、定位 Sicily Italy、联系邮箱 xzmcart@hotmail.com；新增企业图片抓取（官网）与公开证书标注（未核验）；GitHub 推送改用 PAT（token 不入库）。

---

## 1. 架构决策（已锁定）

| 项 | 决策 |
|---|---|
| 线上托管 | **Cloudflare Pages**（仅静态文件） |
| 本地创作 | **WordPress**（PHP + MySQL，后台登录发内容） |
| 上线链路 | 本地 WP 编辑 → 一键静态导出 → `html/` → 推送 GitHub `artcoatings` → Cloudflare Pages 自动部署 |
| 站点根 | Cloudflare Pages 的 **构建输出目录 = `html`**，所有图片/JS/CSS 均在 `html/` 内 |
| 域名 | `artcoatings.org` |

> 关键点：本地 WordPress 不对外，`site/` 与 `html/` 严格分离；`html/` 是唯一对外产物，天然满足"只能用静态文件、资源全在 html 里"。

---

## 2. 目录结构

```
D:\wwwroot\artcoatings.org\
├─ site\                          # WordPress 本体（本地创作，不对外）
│  ├─ wp-config.php               # 本地 DB 配置
│  └─ wp-content\
│     ├─ themes\artcoatings\      # 自研主题（已完成骨架）
│     │  ├─ assets\css\tokens.css # 设计令牌
│     │  ├─ assets\css\main.css   # 响应式主样式（移动优先）
│     │  ├─ assets\js\main.js     # 移动端导航 + 滚动交互
│     │  ├─ front-page.php        # 首页
│     │  ├─ archive-ac_company.php# 黄页列表
│     │  ├─ single-ac_company.php # 企业详情
│     │  ├─ single-ac_news.php    # 新闻详情
│     │  ├─ header.php / footer.php / index.php / page.php / 404.php
│     │  └─ functions.php
│     └─ plugins\artcoatings-core\# 内容模型（CPT + 分类 + 字段）
├─ html\                          # 静态产物（对外站点根；Cloudflare 输出目录）
├─ router.php                     # PHP 内置服务器路由（含 WebP MIME）
├─ start-env.bat                  # 启动 MySQL(3398) + PHP(8080)
├─ .gitignore                     # 忽略本地密钥（.github_token 等，绝不入库）
└─ tools\
   ├─ init-db.sql                 # 建库 + 建用户
   ├─ cli-install.php             # 自动安装 WordPress
   ├─ cli-theme.php               # 启用主题 + 初始化站点（含 About 页）
   ├─ export_xlsx.py              # 从 Excel 抽取结构化字段 → data/companies.json
   ├─ scrape_assets.py            # 从官网抓取图片 + 公开证书 → data/images + enriched.json
   ├─ import-companies.php        # 把数据（含本地图片/证书）导入 ac_company
   ├─ deploy.sh / upload-github.bat # 同步 html/ → GitHub 并推送（PAT 鉴权）
   ├─ .github_token               # 本地 GitHub PAT（gitignore，不入库）
   └─ data\                       # 中间产物（companies.json / enriched.json / images/）
```

---

## 3. 内容模型（artcoatings-core 插件）

| 类型/分类 | 标识 | 用途 | 关键字段 |
|---|---|---|---|
| 企业黄页 | `ac_company` | 黄页主体，企业展示与引流 | 官网、电话、联系人、地址、成立年份、主营产品、服务地区、资质证书（未核验）、一句话简介；特色图片=Logo/封面，图集=官网抓取 |
| 新闻 | `ac_news` | 行业资讯 | 标题、正文、摘要、封面、来源 |
| 行业分类 | `ac_industry` | 企业按品类筛选 | 层级分类 |
| 地区分类 | `ac_region` | 企业按地区筛选 | 层级分类 |
| 新闻分类 | `ac_news_cat` | 新闻归档 | 层级分类 |

**设计取舍**：字段用**原生 meta**（不依赖 ACF），导出时零插件耦合，静态化更稳。若后续需要更复杂的字段 UI，再评估引入 ACF。

URL 结构：`/company/{slug}/`、`/news/{slug}/`、`/industry/{term}/`、`/region/{term}/`。

---

## 4. 设计系统（tokens.css）

| Token | 值 | 用途 |
|---|---|---|
| `--ink` | `#141414` | 主深色（页头/页脚/Hero） |
| `--gold` | `#b8965f` | 香槟金主强调色 / 主按钮 |
| `--gold-strong` | `#9f7f45` | 金色 hover / 链接 |
| `--bg-muted` | `#f7f5f1` | 浅底区块 |
| `--line` | `#e8e4dc` | 分割线 / 边框 |
| `--text` / `--text-muted` | `#1a1a1a` / `#6b6b6b` | 正文 / 次要文字 |
| `--container` | `1200px` | 内容最大宽度 |
| `--radius` | `2px` | 克制的圆角（对齐 USLION 硬朗质感） |

**响应式（移动优先）**：≤639 单列 / ≥640 双列 / ≥768 放大字号与间距 / ≥1024 三列 + 桌面导航 / ≥1200 大屏微调。

---

## 5. 静态导出与部署

### 5.1 导出（推荐 Simply Static）
1. 后台安装并启用 **Simply Static**
2. 设置：
   - *Destination* = **Local Directory → `D:\wwwroot\artcoatings.org\html`**
   - *Export Type* = Full Site
   - *URL Rewriting* = **Relative**（确保 html/ 自包含，Cloudflare 可直接托管）
3. 点击 *Generate Static Files*

> 备选：自研导出脚本（按需再写），可精确控制资源归集。

### 5.2 部署（含 GitHub 鉴权）
```bash
export ARTCOATINGS_REPO="/c/Users/cjx/artcoatings"   # 本地 git clone 目录
bash tools/deploy.sh
```
> 鉴权：GitHub 已不支持密码推送，必须用 PAT。脚本优先读环境变量 `GH_TOKEN`，否则读 `tools/.github_token`（已 gitignore，绝不入库）。推送时通过 `url.insteadOf` 临时注入 token，不会写入任何配置文件。请勿把 PAT 提交到仓库。

### 5.3 Cloudflare Pages
- 连接 GitHub `artcoatings` 仓库
- Framework preset = **None**
- Build command = 空
- **Build output directory = `html`**
- Custom domain = `artcoatings.org`

---

## 6. 本地启动（三步）

```bash
# 1) 建库（需 MySQL 3398 已启动；root 或有 GRANT 权限的账号）
mysql -h 127.0.0.1 -P 3398 -u root -p < tools/init-db.sql

# 2) 安装 WordPress
<runtime>\php\php.exe tools/cli-install.php

# 3) 启用主题 + 初始化
<runtime>\php\php.exe tools/cli-theme.php
```
或直接双击 `start-env.bat` 启动服务，访问 http://localhost:8080/

**管理员**：`admin` / `Admin@2026!ac`

---

## 7. 与 xzmcgeo 的一致性
- 复用 meizhous 的本地环境模式（PHP 内置服务器 + router.php + MySQL 3398）
- 复用其「主题 + core 插件 + CPT」工程结构
- 视觉沿用 USLION 深黑/香槟金调性

---

## 8. 后续待办（确认后推进）
1. 运行环境、装 WP、录入 3–5 家样例企业与 2–3 篇新闻，跑通首页/列表/详情
2. 接入 Simply Static，验证 `html/` 自包含
3. 建 GitHub 仓库 + Cloudflare Pages，配置自定义域名
4. 按真实数据打磨设计细节（企业卡片、详情侧栏、移动端筛选）
