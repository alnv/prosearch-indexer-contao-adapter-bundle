<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction;

final class HtmlTextExtractor
{
    /**
     * Elements whose content is never part of the page text.
     */
    private const array REMOVE_WITH_CONTENT = ['script', 'style', 'noscript', 'template', 'svg', 'iframe', 'object', 'canvas', 'select', 'datalist', 'textarea'];

    /**
     * Inline elements that do not separate words.
     */
    private const array INLINE = ['a', 'abbr', 'b', 'bdi', 'bdo', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'font', 'i', 'ins', 'kbd', 'mark', 'q', 's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr'];

    /**
     * Block elements that end a paragraph.
     */
    private const array BLOCK = ['address', 'article', 'aside', 'blockquote', 'br', 'dd', 'details', 'dialog', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul', 'caption', 'legend', 'body'];

    public function extract(string $html): string
    {
        $html = $this->removeIndexerStopBlocks($html);
        $html = $this->body($html);

        foreach (self::REMOVE_WITH_CONTENT as $tag) {
            $html = $this->removeElements($html, $tag);
        }

        $html = $this->removeComments($html);

        $inline = array_flip(self::INLINE);
        $block = array_flip(self::BLOCK);

        $text = preg_replace_callback(
            '/<\/?([a-zA-Z][a-zA-Z0-9:-]*)(?:\s[^>]*)?\/?>/',
            static function (array $match) use ($inline, $block): string {
                $tag = strtolower($match[1]);

                if (isset($inline[$tag])) {
                    // closing inline tags may still separate words in some layouts (e.g. adjacent links)
                    return 'a' === $tag && str_starts_with($match[0], '</') ? ' ' : '';
                }

                return isset($block[$tag]) ? "\n" : ' ';
            },
            $html,
        );

        // regex failure (backtrack limit on broken markup): fall back to strip_tags
        $text = strip_tags($text ?? str_replace('<', ' <', $html));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');

        return TextNormalizer::normalize($text);
    }

    private function body(string $html): string
    {
        $headEnd = stripos($html, '</head>');

        if (false !== $headEnd) {
            $html = substr($html, $headEnd + 7);
        }

        return $html;
    }

    /**
     * Removes <!-- indexer::stop --> … <!-- indexer::continue --> incl. nested blocks.
     */
    private function removeIndexerStopBlocks(string $content): string
    {
        $stop = '<!-- indexer::stop -->';
        $continue = '<!-- indexer::continue -->';

        while (false !== ($start = strpos($content, $stop))) {
            $depth = 0;
            $position = $start;
            $end = false;

            while (true) {
                $nextStop = strpos($content, $stop, $position + 1);
                $nextContinue = strpos($content, $continue, $position + 1);

                if (false === $nextContinue) {
                    break;
                }

                if (false !== $nextStop && $nextStop < $nextContinue) {
                    ++$depth;
                    $position = $nextStop;

                    continue;
                }

                if (0 === $depth) {
                    $end = $nextContinue;

                    break;
                }

                --$depth;
                $position = $nextContinue;
            }

            if (false === $end) {
                // unbalanced marker: everything after it is excluded (as the Contao core does)
                return substr($content, 0, $start);
            }

            $content = substr($content, 0, $start) . substr($content, $end + \strlen($continue));
        }

        return $content;
    }

    private function removeElements(string $html, string $tag): string
    {
        $offset = 0;

        while (false !== ($start = stripos($html, '<' . $tag, $offset))) {
            $next = $html[$start + \strlen($tag) + 1] ?? '';

            // "<s" must not match "<span", "<select" must not match "<selection"
            if ('' !== $next && !\in_array($next, [' ', '>', '/', "\n", "\t", "\r"], true)) {
                $offset = $start + 1;

                continue;
            }

            $end = stripos($html, '</' . $tag, $start);

            if (false === $end) {
                $close = strpos($html, '>', $start);

                return false === $close ? substr($html, 0, $start) : substr($html, 0, $start) . substr($html, $close + 1);
            }

            $close = strpos($html, '>', $end);
            $html = substr($html, 0, $start) . ' ' . substr($html, false === $close ? \strlen($html) : $close + 1);
            $offset = $start;
        }

        return $html;
    }

    private function removeComments(string $html): string
    {
        while (false !== ($start = strpos($html, '<!--'))) {
            $end = strpos($html, '-->', $start + 4);
            $html = substr($html, 0, $start) . (false === $end ? '' : substr($html, $end + 3));
        }

        return $html;
    }
}
