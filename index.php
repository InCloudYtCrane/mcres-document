<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("依赖未安装，请先运行：composer install\n");
}
require $autoload;

use App\Docs;
use App\MarkdownRenderer;

/* ===================== 加载配置 ===================== */
$config = require __DIR__ . '/config.php';
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, require __DIR__ . '/config.local.php');
}
if (getenv('APP_PORT'))  { $config['port']  = (int) getenv('APP_PORT'); }
if (getenv('APP_DEBUG')) { $config['debug'] = (bool) getenv('APP_DEBUG'); }
if (getenv('DOCS_DIR'))  { $config['docs_dir'] = getenv('DOCS_DIR'); }

/* ===================== 调试模式 ===================== */
if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

/* ===================== 工具函数 ===================== */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 从导航树里找出当前页对应的 title
 */
function findNavTitle(array $items, string $currentUrl): ?string
{
    foreach ($items as $item) {
        if (!empty($item['url']) && $item['url'] === $currentUrl) {
            return $item['title'];
        }
        if (!empty($item['children'])) {
            $found = findNavTitle($item['children'], $currentUrl);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

/* ===================== 初始化 ===================== */
$docs = new Docs((string) $config['docs_dir']);

/* ===================== 解析请求路径 ===================== */
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$page    = trim(rawurldecode($rawPath), '/');

/* ===================== API: 搜索 ===================== */
if ($page === 'api/search') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        $docs->search((string) ($_GET['q'] ?? '')),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/* ===================== 首页映射 ===================== */
if ($page === '') {
    $page = 'README';
}

/* ===================== 解析文件 ===================== */
$file = $docs->resolve($page);

$siteName    = (string) ($config['site_name'] ?? '帮助文档');
$titlePrefix = (string) ($config['title_prefix'] ?? '');

/* ===================== 404 ===================== */
if ($file === null) {
    http_response_code(404);
    $pageTitle = '页面不存在';
    $content   = '<h1>页面不存在</h1><p>你访问的文档没有找到。</p>';
    $toc       = '';
    $nav       = $docs->buildNav('');
    require __DIR__ . '/templates/page.php';
    exit;
}

$raw = (string) file_get_contents($file);

/* ===================== 计算当前页 URL ===================== */
$currentPage = ($page === 'README') ? '' : $page;
$currentUrl  = ($currentPage === '') ? '/' : '/' . $currentPage;

/* ===================== 构建导航 ===================== */
$nav = $docs->buildNav($currentPage);

/* ===================== 页面标题：取侧边栏菜单上的文字 ===================== */
$pageTitle = findNavTitle($nav, $currentUrl);

if ($pageTitle === null) {
    // 兜底：用文件名
    $stem      = basename($file, '.md');
    $pageTitle = ($stem === 'README') ? '首页' : $docs->cleanName($stem);
}

/* ===================== 渲染 Markdown ===================== */
$renderer = new MarkdownRenderer();
$rendered = $renderer->render($raw);
$content  = $rendered['html'];
$toc      = $rendered['toc'];

require __DIR__ . '/templates/page.php';