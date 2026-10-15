<?php
declare(strict_types=1);

namespace App;

use DOMDocument;
use DOMElement;
use DOMXPath;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

final class MarkdownRenderer
{
    private const ROOT_ID = '__cm_root__';

    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => 100,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new FootnoteExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new DescriptionListExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /** @return array{html:string, toc:string} */
    public function render(string $markdown): array
    {
        $html = (string) $this->converter->convert($markdown);
        return $this->processHtml($html);
    }

    /** @return array{html:string, toc:string} */
    private function processHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return ['html' => '', 'toc' => ''];
        }

        $doc  = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);

        $loaded = $doc->loadHTML(
            '<html><head><meta charset="utf-8"></head><body>'
            . '<div id="' . self::ROOT_ID . '">'
            . $html
            . '</div></body></html>'
        );

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            return ['html' => $html, 'toc' => ''];
        }

        $xpath    = new DOMXPath($doc);
        $rootNode = $xpath->query('//div[@id="' . self::ROOT_ID . '"]')->item(0);

        if (!$rootNode instanceof DOMElement) {
            return ['html' => $html, 'toc' => ''];
        }

        $headingNodes = $xpath->query('.//h1|.//h2|.//h3|.//h4', $rootNode);

        $flat = [];
        $used = [];

        foreach ($headingNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $text = trim($node->textContent);
            if ($text === '') {
                continue;
            }

            $base = $this->slugify($text);
            if ($base === '') {
                $base = 'section';
            }

            $id = $base;
            $n  = 2;
            while (isset($used[$id])) {
                $id = $base . '-' . $n;
                $n++;
            }
            $used[$id] = true;

            $node->setAttribute('id', $id);

            $flat[] = [
                'level' => (int) substr(strtolower($node->nodeName), 1),
                'id'    => $id,
                'text'  => $text,
            ];
        }

        $content = '';
        foreach ($rootNode->childNodes as $child) {
            $content .= $doc->saveHTML($child);
        }

        return [
            'html' => $content,
            'toc'  => $this->buildToc($flat),
        ];
    }

    private function buildToc(array $flat): string
    {
        if (empty($flat)) {
            return '';
        }

        $items = [];
        $stack = [];

        foreach ($flat as $entry) {
            $level = $entry['level'];

            while (!empty($stack)
                && $items[$stack[count($stack) - 1]]['level'] >= $level) {
                array_pop($stack);
            }

            $parent = empty($stack) ? null : $stack[count($stack) - 1];

            $items[] = [
                'level'  => $level,
                'id'     => $entry['id'],
                'text'   => $entry['text'],
                'parent' => $parent,
            ];
            $stack[] = count($items) - 1;
        }

        $byParent = ['' => []];
        foreach ($items as $i => $item) {
            $key = $item['parent'] === null ? '' : (string) $item['parent'];
            $byParent[$key][] = $i;
        }

        return '<div class="toc"><ul>'
            . $this->renderTocLevel($items, $byParent, '')
            . '</ul></div>';
    }

    private function renderTocLevel(array $items, array $byParent, string $key): string
    {
        if (empty($byParent[$key])) {
            return '';
        }

        $out = '';
        foreach ($byParent[$key] as $index) {
            $item = $items[$index];

            $out .= '<li><a href="#'
                . htmlspecialchars($item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '">'
                . htmlspecialchars($item['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</a>';

            $children = $this->renderTocLevel($items, $byParent, (string) $index);
            if ($children !== '') {
                $out .= '<ul>' . $children . '</ul>';
            }

            $out .= '</li>';
        }

        return $out;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $text) ?? $text;
        $text = preg_replace('/[\s_]+/u', '-', $text) ?? $text;
        $text = preg_replace('/-+/u', '-', $text) ?? $text;
        return trim($text, '-');
    }
}