<?php
declare(strict_types=1);

namespace App;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Docs
{
    private string $dir;
    private string $realDir;

    public function __construct(string $dir)
    {
        $this->dir     = rtrim($dir, '/\\');
        $real          = realpath($this->dir);
        $this->realDir = $real !== false ? $real : $this->dir;
    }

    /* ===================== 名称清理 & 排序 ===================== */

    public function cleanName(string $name): string
    {
        $cleaned = preg_replace('/^\d+[-_]\s*/u', '', $name);
        return $cleaned === null ? $name : $cleaned;
    }

    /** @return array{0:int,1:int,2:string} */
    private function sortKey(string $name): array
    {
        if (preg_match('/^(\d+)/', $name, $m)) {
            return [0, (int) $m[1], $name];
        }
        return [1, 0, $name];
    }

    /* ===================== 路径安全解析 ===================== */

    public function resolve(string $page): ?string
    {
        if ($page === '') {
            $page = 'README';
        }
        if (strpos($page, "\0") !== false) {
            return null;
        }

        $normalized = str_replace('\\', '/', $page);
        $parts      = explode('/', $normalized);
        if (in_array('..', $parts, true)) {
            return null;
        }

        $candidates = [
            $this->dir . '/' . $normalized . '.md',
            $this->dir . '/' . $normalized . '/README.md',
        ];

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real === false || !is_file($real)) {
                continue;
            }
            if (strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'md') {
                continue;
            }
            if ($real !== $this->realDir
                && strpos($real, $this->realDir . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }
            return $real;
        }

        return null;
    }

    /* ===================== 导航树 ===================== */

    public function buildNav(string $currentPage = ''): array
    {
        $currentUrl = ($currentPage === '' || $currentPage === 'README')
            ? '/'
            : '/' . $currentPage;

        return $this->scanDir($this->dir, [], $currentUrl);
    }

    private function scanDir(string $dir, array $pathParts, string $currentUrl): array
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }

        $entries = array_values(array_filter(
            $entries,
            static fn(string $e): bool => $e !== '.' && $e !== '..'
        ));

        usort($entries, fn(string $a, string $b): int
            => $this->sortKey($a) <=> $this->sortKey($b));

        $items = [];

        foreach ($entries as $name) {
            if ($name === 'README.md') {
                continue;
            }

            $full = $dir . DIRECTORY_SEPARATOR . $name;

            if (is_dir($full)) {
                $children = $this->scanDir(
                    $full,
                    array_merge($pathParts, [$name]),
                    $currentUrl
                );

                $groupUrl = '/' . implode('/', array_merge($pathParts, [$name]));

                $expanded = ($currentUrl === $groupUrl)
                    || strpos($currentUrl, $groupUrl . '/') === 0;

                $item = [
                    'title'    => $this->cleanName($name),
                    'children' => $children,
                    'expanded' => $expanded,
                    'active'   => false,
                    'group_id' => implode('/', array_merge($pathParts, [$name])),
                    'url'      => null,
                ];

                $readme = $full . DIRECTORY_SEPARATOR . 'README.md';
                if (is_file($readme)) {
                    $item['url']    = $groupUrl;
                    $item['active'] = ($currentUrl === $groupUrl);
                }

                if (!empty($children) || is_file($readme)) {
                    $items[] = $item;
                }

            } elseif (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'md') {
                $stem = substr($name, 0, -3);
                $url  = '/' . implode('/', array_merge($pathParts, [$stem]));

                $items[] = [
                    'title'    => $this->cleanName($stem),
                    'children' => [],
                    'expanded' => false,
                    'active'   => ($url === $currentUrl),
                    'group_id' => null,
                    'url'      => $url,
                ];
            }
        }

        if (empty($pathParts) && is_file($dir . DIRECTORY_SEPARATOR . 'README.md')) {
            array_unshift($items, [
                'title'    => '首页',
                'children' => [],
                'expanded' => false,
                'active'   => ($currentUrl === '/'),
                'group_id' => null,
                'url'      => '/',
            ]);
        }

        return $items;
    }

    /* ===================== 全文搜索 ===================== */

    public function search(string $q, int $limit = 20): array
    {
        $q = trim(mb_strtolower($q, 'UTF-8'));
        if ($q === '') {
            return [];
        }

        $qLen    = mb_strlen($q, 'UTF-8');
        $results = [];

        foreach ($this->allMarkdownFiles() as $file) {
            $rel = str_replace('\\', '/', substr($file, strlen($this->realDir) + 1));
            $rel = preg_replace('/\.md$/i', '', $rel) ?? $rel;

            $parts = explode('/', $rel);
            if (end($parts) === 'README') {
                array_pop($parts);
            }

            if (!empty($parts)) {
                $url   = '/' . implode('/', $parts);
                $title = $this->cleanName((string) end($parts));
            } else {
                $url   = '/';
                $title = '首页';
            }

            $raw  = (string) @file_get_contents($file);
            $text = preg_replace('/[#*`>\[\]\(\)!]/u', ' ', $raw) ?? $raw;
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            $text = trim($text);

            $textLower  = mb_strtolower($text, 'UTF-8');
            $titleLower = mb_strtolower($title, 'UTF-8');

            if (mb_strpos($textLower, $q) === false
                && mb_strpos($titleLower, $q) === false) {
                continue;
            }

            $idx = mb_strpos($textLower, $q);

            if ($idx === false) {
                $snippet = mb_substr($text, 0, 60, 'UTF-8');
                if (mb_strlen($text, 'UTF-8') > 60) {
                    $snippet .= '...';
                }
            } else {
                $start = max(0, $idx - 30);
                $end   = min(mb_strlen($text, 'UTF-8'), $idx + $qLen + 60);
                $snippet = mb_substr($text, $start, $end - $start, 'UTF-8');
                if ($start > 0) {
                    $snippet = '...' . $snippet;
                }
                if ($end < mb_strlen($text, 'UTF-8')) {
                    $snippet .= '...';
                }
            }

            $results[] = [
                'title'   => $title,
                'url'     => $url,
                'snippet' => trim($snippet),
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /** @return string[] */
    private function allMarkdownFiles(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->dir,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files);
        return $files;
    }
}