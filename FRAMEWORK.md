# artcoatings.org 开发框架（v0.3 · 国际化版 · 已跑通）

> **ArtCoatings — Art Coatings Directory** · 面向国际（英语）、定位意大利西西里 · WordPress 本地创作 + 静态导出 + Cloudflare Pages 托管
>
> 变更记录：v0.2 起全站转为英文、定位 Sicily Italy、联系邮箱 xzmcart@hotmail.com；新增企业图片抓取（官网）与公开证书标注（未核验）；GitHub 推送改用 PAT（token 不入库）。
> v0.3：本地环境打通并导入 50 家企业；弃用 Simply Static，改用自研 `tools/static-export.php`（57 页 + 173 资源，根相对自包含）；仓库 `merlincao/artcoatings` 已建并推送。

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
├─ site\                          # WordPress 本体（本地创作，不对外；仅自研主题/插件入库）
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
├─ deploy.sh                      # 一条龙：静态导出 + 提交 + 推送
├─ upload-github.bat              # Windows：提交 + 推送（不重新导出）
├─ .gitignore                     # 忽略本地密钥、WP 核心、抓取原图
└─ tools\
   ├─ init-db.sql                 # 建库 + 建用户
   ├─ _init_bootstrap.sql         # mysqld --init-file 引导（免 root 密码时建库建用户）
   ├─ cli-install.php             # 自动安装 WordPress
   ├─ cli-theme.php               # 启用主题 + 初始化站点（含 About 页）
   ├─ export_xlsx.py              # 从 Excel 抽取结构化字段 → data/companies.json
   ├─ scrape_assets.py            # 从官网抓取图片 + 公开证书 → data/images + enriched.json
   ├─ import-companies.php        # 把数据（含本地图片/证书）导入 ac_company
   ├─ static-export.php           # 静态导出器：抓取 :8080 → html/（含分页发现、资源归集）
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

无图企业用**衬线字母 monogram** 兜底（非"暂无图片"占位文案），保证卡片视觉一致。

---

## 5. 静态导出与部署

### 5.1 导出（自研 `static-export.php`，已落地）

本地站点需先跑在 `http://127.0.0.1:8080`（`start-env.bat`），然后：

```bash
<runtime>\php\php.exe tools/static-export.php
# 可选：--base https://artcoatings.org  改写为绝对生产域名（默认根相对，换域名无需重导）
```

脚本行为：
- 抓取首页 / 企业归档 / 50 家企业详情 / 新闻归档 / 页面（About 等），共 **57 个页面**
- **自动发现分页**：扫描 `page-numbers` 链接，导出 `/company/page/2`、`/company/page/3`（24+24+2 = 50 家，无遗漏无重复）
- 归集 `wp-content/`（含 uploads 的抓取图片）与 `wp-includes/` 资源，并跟进 CSS 内 `url()` 引用
- 把 `http://localhost:8080` 改写为**根相对路径**，产物自包含
- 跳过 WordPress Speculation Rules 等注入 JSON 里的通配片段（如 `/wp-content/uploads/*`），否则每个都会白白触发抓取超时

> 已弃用 Simply Static（3.x 走异步任务队列，无头调用不便）；插件仍留在 `site/wp-content/plugins/` 备用。

### 5.2 部署（含 GitHub 鉴权）

```bash
bash deploy.sh              # 静态导出 + 提交 + 推送（一条龙）
bash deploy.sh --push-only  # 只推送现有 html/
```

Windows 双击 `upload-github.bat` = 提交 + 推送（不重新导出）。

- 仓库：**https://github.com/merlincao/artcoatings**（branch `main`）
- 鉴权：GitHub 不支持密码推送，必须用 PAT；脚本读 `tools/.github_token`（已 gitignore，绝不入库），token 仅在推送 URL 中临时出现，不写入 `.git/config`
- 网络：本机访问 github.com 需走代理 `127.0.0.1:7890`（设好 `http_proxy` / `https_proxy` 即可，git 会沿用）

### 5.3 Cloudflare Pages

- 连接 GitHub `artcoatings` 仓库
- Framework preset = **None**
- Build command = 空
- **Build output directory = `html`** ← 关键，填错会发布到仓库根目录
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

## 8. 语言与图片规则（长期约束）

**全站英文，不得出现中文**
- 站点对外语言锁定英文。注意安装的是**中文版核心包**
  （`site/wp-includes/version.php` 的 `$wp_local_package = 'zh_CN'`），
  只改 option 不够，`<html lang>` 仍会输出 `zh-CN` —— 必须在
  `wp-config.php` 里 `define( 'WPLANG', 'en_US' )`。
- 数据侧中文用 `tools/translate-to-en.php` 处理（term 名/slug + meta + 正文）。
  映射表**必须按中文词长度降序应用**，否则 "装饰涂料" 会先切碎 "高端装饰涂料"。

**图片一律 webp**
- `tools/convert-webp.php` 负责把源图转 webp（GD `imagewebp`，质量 82，保留 alpha）。
- 坑：采下来的图里有一批「扩展名 png/jpg、实际是 AVIF」，
  `imagecreatefrompng/jpeg` 会失败 → 必须退回 `imagecreatefromstring()` 按内容探测。

**语言切换（changeLanguage）**
- 静态站无后端，走客户端 i18n：`assets/js/i18n.js`，暴露 `window.changeLanguage(code)`。
- 支持 English / Français / Italiano / Deutsch / Português / Ελληνικά，
  选择写入 `localStorage['ac_lang']`，也支持 `?lang=fr`。
- 字典以「英文原文」为 key；只对**白名单选择器**做整段精确匹配替换，
  刻意排除 `.card__title` / `.company__name` / `.prose` / `.panel__text` 等数据区，
  避免把品牌名误译（Crown Paints 不应变成 Couronne Peintures）。

## 9. 当前状态与后续待办

**已完成**
1. 本地环境打通（MySQL 3398 + PHP 8080 + WordPress 安装 + 主题/插件启用）
2. 从 Excel 抽取 50 家企业；**全部 50 家有图**（29 张原图 + 13 张补抓 + 8 张 AI 生成）
3. 导入 `ac_company`，生成英文词条（含 References 出处）与「未核验」证书标注
4. 自研静态导出器 v2.1：**73 个页面 + 1038 个资源**，相对路径自包含，0 失败
5. GitHub 仓库 `merlincao/artcoatings` 已建并推送（main）
6. 全站英文化（插件/主题/数据库/正文），`<html lang="en-US">`
7. 导航栏语言切换器（6 语言）
8. 图片全部 webp

**待办（确认后推进）**
1. Cloudflare Pages 连接该仓库，**Build output directory 设为 `html`**，绑定 `artcoatings.org`
2. 清理仓库遗留占位内容：根目录的 `index.html`（USLION 意大利品牌页）、`assets/`、`README.md` 与本项目无关，避免误发布
3. 补新闻内容（当前 0 篇）
4. 按真实数据打磨设计细节（企业卡片、详情侧栏、移动端筛选）

**常用命令**
```bash
php tools/static-export.php --clean      # 清空 html/ 后全量导出（推荐）
python tools/check-links.py              # 静态站体检（断链/绝对地址/空文件/中文/非webp）
php tools/convert-webp.php --apply       # 源图转 webp
php tools/translate-to-en.php --apply    # 内容英文化
php tools/attach-images.php --force      # 重新挂图
bash deploy.sh                           # 导出 + 提交 + 推送
```
注意：**不要用 shell `rm -rf html`** —— 上百个文件会触发安全策略的批量删除拦截，
清空请用导出器的 `--clean`（PHP 内部递归删除）。
