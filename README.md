# 方糖文档

一个轻量、开箱即用的 Markdown 文档站点。把 `.md` 文件丢进 `docs/` 目录，页面自动生成侧边栏、目录、搜索——不需要数据库，不需要后台，不需要构建。

> 名字取自「方糖」——简单一块，泡开就是一杯好文档。

---

## 特性

- **零配置启动** — 把 Markdown 丢进 `docs/`，导航自动生成
- **树形目录** — 支持多级文件夹，数字前缀自动排序
- **右侧 TOC** — 自动生成页面大纲，滚动高亮当前章节
- **全文搜索** — 内置轻量搜索，无需 Elasticsearch 之类依赖
- **响应式** — 桌面三栏、移动单栏，手机端菜单变全屏页
- **简洁美观** — 无 CSS 框架，一份 style.css 说清楚
- **CommonMark 渲染** — 支持表格、任务列表、脚注、删除线、定义列表等
- **PHP 8.1+** — 单入口，跑在任何支持 PHP 的服务器上

---

## 快速开始

### 环境要求

- PHP **≥ 8.1**
- Composer 2.x
- 任意 Web 服务器（Nginx / Apache / PHP 内置服务器）

### 安装

```bash
# 1. 克隆仓库
git clone https://gitee.com/mcresource/mcres-document.git
cd mcres-document

# 2. 安装依赖
composer install --no-dev --optimize-autoloader

# 3. 放一篇文档
echo "# 欢迎使用方糖文档" > docs/README.md

# 4. 启动（开发模式）
php -S 0.0.0.0:5006 router.php
```

打开 <http://localhost:5006> 就能看到页面。

---

## 目录结构

```
方糖文档/
├── docs/                    # 你的 Markdown 文档放这里
│   ├── README.md            # 首页
│   ├── 1-快速开始.md
│   └── 2-进阶/
│       ├── README.md        # 该目录的首页
│       └── 配置说明.md
├── lib/
│   ├── Docs.php             # 文档扫描、导航树、搜索
│   └── MarkdownRenderer.php # Markdown 转 HTML + TOC
├── templates/
│   └── page.php             # 页面模板
├── static/
│   ├── style.css
│   └── script.js
├── config.php               # 站点配置（提交到 Git）
├── index.php                # 入口与路由
└── router.php               # PHP 内置服务器路由
```

---

## 配置

编辑 `config.php`：

```php
<?php
return [
    // 站点名称（侧边栏顶部显示）
    'site_name' => '方糖文档',

    // 浏览器标题前缀，最终显示为 "方糖 - 快速开始"
    'title_prefix' => '方糖',

    // 文档目录（相对或绝对路径）
    'docs_dir' => __DIR__ . '/docs',

    // 监听端口（仅 php -S 用）
    'port' => 5006,

    // 调试模式：true 会显示详细错误
    'debug' => false,
];
```

### 私有覆盖

不想改动主配置，可以创建 `config.local.php`（已在 `.gitignore` 中）：

```php
<?php
return [
    'site_name' => '我自己的文档',
    'debug' => false,
];
```

### 环境变量

| 变量 | 覆盖项 |
| --- | --- |
| `DOCS_DIR` | `docs_dir` |
| `APP_PORT` | `port` |
| `APP_DEBUG` | `debug` |

---

## 写文档

### 文件名决定排序

用数字前缀控制顺序，前缀不会显示在界面上：

```
docs/
├── README.md              → 首页
├── 1-安装.md              → 「安装」
├── 2-使用.md              → 「使用」
└── 3-进阶/
    ├── README.md          → 「进阶」
    └── 1-配置.md          → 「配置」
```

- 数字前缀按数值大小排序
- 无数字前缀的按字母或拼音排序

### 支持的 Markdown 语法

在标准 Markdown 之外，还支持：

| 语法 | 效果 |
| --- | --- |
| `~~删除线~~` | 删除线 |
| `- [x] 任务` | 任务列表 |
| 表格（GFM） | 表格 |
| `[^1]` | 脚注 |
| `https://example.com` | 裸链接自动识别 |
| `{#custom-id .my-class}` | 自定义标题 id 与类名 |
| 定义列表 | 术语加描述 |

---

## 部署

### Nginx（推荐）

```nginx
server {
    listen 80;
    server_name docs.example.com;
    root /var/www/fangtang-docs;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/(docs|vendor|lib|templates|runtime)/ {
        deny all;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Apache

`.htaccess` 已经写好，把项目放进 `DocumentRoot` 并启用 `mod_rewrite` 即可。

### 宝塔面板

1. 新建站点，PHP 版本选 8.1 以上
2. 上传项目到站点目录，执行 `composer install`
3. 网站设置 → 伪静态 → 填入：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/(docs|vendor|lib|templates|runtime)/ {
    deny all;
}
```

4. 网站目录 → 运行目录保持 `/`
5. 确保目录所有者为 `www`，权限 `755`

---

## API

内置一个搜索接口：

```
GET /api/search?q=关键词
```

返回 JSON 数组：

```json
[
  {
    "title": "快速开始",
    "url": "/快速开始",
    "snippet": "...欢迎使用方糖文档，这里教你如何..."
  }
]
```

---

## 参与贡献

欢迎提 Issue 和 PR。

```bash
# 开发模式启动
php -S 0.0.0.0:5006 router.php

# 调试模式（显示详细错误）
APP_DEBUG=1 php -S 0.0.0.0:5006 router.php
```

---

## 许可证

[MIT](LICENSE) © MCRES