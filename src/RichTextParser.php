<?php

declare(strict_types=1);

namespace Croct\Plug\Storyblok;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Converts Markdown into a Storyblok rich text document.
 *
 * It reads CommonMark with strikethrough, the format in which rich text is synced to Croct.
 * Raw HTML is read as plain text, since the sync escapes it.
 */
final class RichTextParser
{
    private MarkdownParser $parser;

    public function __construct()
    {
        $environment = new Environment();

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TaskListExtension());

        $this->parser = new MarkdownParser($environment);
    }

    /**
     * Converts Markdown into a Storyblok rich text document.
     *
     * @return array<string, mixed>
     *
     * @throws CommonMarkException If the Markdown cannot be parsed.
     */
    public function parse(string $markdown): array
    {
        return [
            'type' => 'doc',
            'content' => self::convertBlocks($this->parser->parse($markdown)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function convertBlocks(Node $parent): array
    {
        $blocks = [];

        foreach ($parent->children() as $child) {
            $block = self::convertBlock($child);

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function convertBlock(Node $node): ?array
    {
        return match (true) {
            $node instanceof Paragraph => [
                'type' => 'paragraph',
                'content' => self::convertInline($node),
            ],
            $node instanceof Heading => [
                'type' => 'heading',
                'attrs' => ['level' => $node->getLevel()],
                'content' => self::convertInline($node),
            ],
            $node instanceof BlockQuote => [
                'type' => 'blockquote',
                'content' => self::convertBlocks($node),
            ],
            $node instanceof ListBlock => self::convertList($node),
            $node instanceof FencedCode => self::createCodeBlock($node->getLiteral(), $node->getInfoWords()[0] ?? ''),
            $node instanceof IndentedCode => self::createCodeBlock($node->getLiteral(), ''),
            $node instanceof ThematicBreak => ['type' => 'horizontal_rule'],
            // Raw HTML is kept as text, as the sync escapes it
            $node instanceof HtmlBlock => [
                'type' => 'paragraph',
                'content' => self::appendText([], $node->getLiteral(), []),
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertList(ListBlock $list): array
    {
        $data = $list->getListData();
        $ordered = $data->type === ListBlock::TYPE_ORDERED;
        $items = [];

        foreach ($list->children() as $item) {
            // Tight and loose list items both hold paragraphs, as Storyblok expects
            $items[] = [
                'type' => 'list_item',
                'content' => self::convertBlocks($item),
            ];
        }

        return [
            'type' => $ordered ? 'ordered_list' : 'bullet_list',
            ...($ordered ? ['attrs' => ['order' => $data->start ?? 1]] : []),
            'content' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function createCodeBlock(string $code, string $language): array
    {
        // The literal of a code block ends with the line break before its closing fence
        $text = \str_ends_with($code, "\n") ? \substr($code, 0, -1) : $code;

        return [
            'type' => 'code_block',
            'attrs' => ['class' => $language === '' ? null : 'language-' . $language],
            'content' => $text === ''
                ? []
                : [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $marks
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private static function convertInline(Node $parent, array $marks = [], array $nodes = []): array
    {
        foreach ($parent->children() as $child) {
            $nodes = match (true) {
                $child instanceof Strong => self::convertInline($child, [...$marks, ['type' => 'bold']], $nodes),
                $child instanceof Emphasis => self::convertInline($child, [...$marks, ['type' => 'italic']], $nodes),
                $child instanceof Strikethrough => self::convertInline(
                    $child,
                    [...$marks, ['type' => 'strike']],
                    $nodes,
                ),
                $child instanceof Link => self::convertInline(
                    $child,
                    [...$marks, self::createLinkMark($child->getUrl())],
                    $nodes,
                ),
                $child instanceof Code => self::appendText(
                    $nodes,
                    $child->getLiteral(),
                    [...$marks, ['type' => 'code']],
                ),
                $child instanceof Image => [...$nodes, self::createImage($child)],
                // Soft line breaks are kept as hard breaks, like the line breaks written in the editor
                $child instanceof Newline => [...$nodes, ['type' => 'hard_break']],
                // Plain text and raw HTML, which is kept as text since the sync escapes it
                $child instanceof AbstractStringContainer => self::appendText(
                    $nodes,
                    // Storyblok has no task lists, so the item keeps only its text
                    $child->previous() instanceof TaskListItemMarker
                        ? \ltrim($child->getLiteral())
                        : $child->getLiteral(),
                    $marks,
                ),
                default => $nodes,
            };
        }

        return $nodes;
    }

    /**
     * Appends a text to the nodes, merging it with the last text node when it has the same marks.
     *
     * Line breaks within the text become hard breaks, since the text is written in a single paragraph.
     *
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $marks
     *
     * @return list<array<string, mixed>>
     */
    private static function appendText(array $nodes, string $text, array $marks): array
    {
        foreach (\explode("\n", $text) as $index => $line) {
            if ($index > 0) {
                $nodes[] = ['type' => 'hard_break'];
            }

            if ($line === '') {
                continue;
            }

            $last = \array_key_last($nodes);

            if ($last !== null
                && $nodes[$last]['type'] === 'text'
                && ($nodes[$last]['marks'] ?? []) === $marks
                && \is_string($nodes[$last]['text'] ?? null)
            ) {
                $nodes[$last]['text'] .= $line;

                continue;
            }

            $nodes[] = [
                'type' => 'text',
                'text' => $line,
                ...($marks === [] ? [] : ['marks' => $marks]),
            ];
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private static function createLinkMark(string $href): array
    {
        $email = \str_starts_with(\strtolower($href), 'mailto:');

        return [
            'type' => 'link',
            'attrs' => [
                'href' => $email ? \substr($href, \strlen('mailto:')) : $href,
                'uuid' => null,
                'anchor' => null,
                'target' => null,
                'linktype' => $email ? 'email' : 'url',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function createImage(Image $image): array
    {
        // Hard breaks in the description become spaces, since it spans a single line
        $alt = \implode('', \array_map(
            static fn (array $node): string => \is_string($node['text'] ?? null) ? $node['text'] : ' ',
            self::convertInline($image),
        ));

        return [
            'type' => 'image',
            'attrs' => [
                'id' => null,
                'src' => $image->getUrl(),
                'alt' => $alt,
                'title' => $image->getTitle(),
                'source' => null,
                'copyright' => null,
                'meta_data' => new \stdClass(),
            ],
        ];
    }
}
