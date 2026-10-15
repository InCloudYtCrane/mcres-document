<?php
/**
 * 可用变量：$pageTitle, $content, $toc, $nav, $siteName, $titlePrefix
 */

if (!function_exists('h')) {
    function h(?string $s): string {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('nav_tree')) {
    function nav_tree(array $items): void
    {
        echo '<ul class="nav">';
        foreach ($items as $item) {
            $hasChildren = !empty($item['children']);

            if ($hasChildren) {
                $collapsed = empty($item['expanded']) ? ' collapsed' : '';
                echo '<li class="nav-group-item' . $collapsed . '" data-group-id="'
                    . h($item['group_id'] ?? '') . '">';

                echo '<div class="nav-group-header" onclick="toggleGroup(this)">';
                $icon = !empty($item['expanded']) ? 'fa-folder-open' : 'fa-folder';
                echo '<i class="fa-solid ' . $icon . ' nav-group-icon"></i>';

                if (!empty($item['url'])) {
                    $active = !empty($item['active']) ? ' active' : '';
                    echo '<a href="' . h($item['url']) . '" class="nav-group-link'
                        . $active . '" onclick="event.stopPropagation()">'
                        . h($item['title']) . '</a>';
                } else {
                    echo '<span class="nav-group-title">' . h($item['title']) . '</span>';
                }

                echo '<i class="fa-solid fa-chevron-right nav-arrow"></i>';
                echo '</div>';

                echo '<div class="nav-children">';
                nav_tree($item['children']);
                echo '</div>';

                echo '</li>';
            } else {
                $active = !empty($item['active']) ? ' active' : '';
                $url    = $item['url'] ?? '#';
                $icon   = ($url === '/')
                    ? 'fa-solid fa-house'
                    : 'fa-regular fa-file-lines';

                echo '<li>';
                echo '<a href="' . h($url) . '" class="' . trim($active) . '">';
                echo '<i class="' . $icon . ' nav-item-icon"></i>';
                echo '<span>' . h($item['title']) . '</span>';
                echo '</a>';
                echo '</li>';
            }
        }
        echo '</ul>';
    }
}

/* ---------- 拼接浏览器标题 ---------- */
$fullTitle = ($titlePrefix !== '')
    ? $titlePrefix . ' - ' . $pageTitle
    : $pageTitle . ' - ' . $siteName;
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="https://img.mcresource.cn/icon/logo/logo_help.png" type="image/x-icon">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/static/style.css">

  <style>
    html:not(.nav-ready) .nav-children { transition: none !important; }
  </style>
</head>
<body>

<div class="mobile-bar">
  <button class="mobile-btn" onclick="openMenu()" aria-label="打开菜单">
    <i class="fa-solid fa-bars"></i>
    <span>菜单</span>
  </button>
  <span class="mobile-title"><?= h($pageTitle) ?></span>
</div>

<div class="layout">

  <aside class="sidebar">
    <div class="sidebar-header">
      <h1 class="logo">
        <i class="fa-solid fa-book-open"></i>
        <span><?= h($siteName) ?></span>
      </h1>
      <button class="mobile-close" onclick="closeMenu()" aria-label="关闭菜单">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="search-box">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" id="search-input" placeholder="搜索文档..." autocomplete="off">
      <div class="search-results" id="search-results"></div>
    </div>

    <div class="nav-wrapper">
      <?php nav_tree($nav); ?>
    </div>
  </aside>

  <script>
    (function () {
      try {
        var states = JSON.parse(localStorage.getItem('navGroupStates') || '{}');
        document.querySelectorAll('.nav-group-item').forEach(function (li) {
          var id = li.dataset.groupId;
          if (!id) return;

          var expanded = (id in states)
            ? !!states[id]
            : !!li.querySelector('a.active');

          li.classList.toggle('collapsed', !expanded);

          var icon = li.querySelector(':scope > .nav-group-header .nav-group-icon');
          if (icon) {
            icon.classList.toggle('fa-folder', !expanded);
            icon.classList.toggle('fa-folder-open', expanded);
          }
        });
      } catch (e) {}
    })();
  </script>

  <main class="content">
    <?= $content ?>
  </main>

  <aside class="toc-sidebar">
    <?php if ($toc !== ''): ?>
      <button class="toc-toggle" onclick="toggleToc()" aria-label="展开或收起目录">
        <i class="fa-solid fa-list-ul"></i>
        <span>本页目录</span>
        <i class="fa-solid fa-chevron-down toc-arrow"></i>
      </button>

      <div class="toc-title">
        <i class="fa-solid fa-list-ul"></i>
        <span>本页目录</span>
      </div>

      <div class="toc-content">
        <?= $toc ?>
      </div>
    <?php endif; ?>
  </aside>

</div>

<script src="/static/script.js"></script>
</body>
</html>