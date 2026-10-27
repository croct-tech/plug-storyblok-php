<?php

declare(strict_types=1);

namespace Croct\Plug\Storyblok\Tests;

use Croct\Plug\Storyblok\RichTextParser;
use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(RichTextParser::class)]
#[TestDox('The rich text parser')]
final class RichTextParserTest extends TestCase
{
    /**
     * @return array<string, array{markdown: string, content: list<array<string, mixed>>}>
     */
    public static function getScenarios(): array
    {
        return [
            'an empty document' => [
                'markdown' => '',
                'content' => [],
            ],
            'paragraphs' => [
                'markdown' => "First paragraph\n\nSecond paragraph",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'First paragraph',
                            ],
                        ],
                    ],
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Second paragraph',
                            ],
                        ],
                    ],
                ],
            ],
            'inline marks' => [
                'markdown' => '**bold** *italic* ~~strike~~ `code` ***both***',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'bold',
                                'marks' => [
                                    [
                                        'type' => 'bold',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' ',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'italic',
                                'marks' => [
                                    [
                                        'type' => 'italic',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' ',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'strike',
                                'marks' => [
                                    [
                                        'type' => 'strike',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' ',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'code',
                                'marks' => [
                                    [
                                        'type' => 'code',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' ',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'both',
                                'marks' => [
                                    [
                                        'type' => 'italic',
                                    ],
                                    [
                                        'type' => 'bold',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'links' => [
                'markdown' => '[url](https://example.com) [story](/blog/post#intro) [email](mailto:jane@example.com)',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'url',
                                'marks' => [
                                    [
                                        'type' => 'link',
                                        'attrs' => [
                                            'href' => 'https://example.com',
                                            'uuid' => null,
                                            'anchor' => null,
                                            'target' => null,
                                            'linktype' => 'url',
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' ',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'story',
                                'marks' => [
                                    [
                                        'type' => 'link',
                                        'attrs' => [
                                            'href' => '/blog/post#intro',
                                            'uuid' => null,
                                            'anchor' => null,
                                            'target' => null,
                                            'linktype' => 'url',
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' ',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'email',
                                'marks' => [
                                    [
                                        'type' => 'link',
                                        'attrs' => [
                                            'href' => 'jane@example.com',
                                            'uuid' => null,
                                            'anchor' => null,
                                            'target' => null,
                                            'linktype' => 'email',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'marks inside a link' => [
                'markdown' => '[**bold** link](https://example.com)',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'bold',
                                'marks' => [
                                    [
                                        'type' => 'link',
                                        'attrs' => [
                                            'href' => 'https://example.com',
                                            'uuid' => null,
                                            'anchor' => null,
                                            'target' => null,
                                            'linktype' => 'url',
                                        ],
                                    ],
                                    [
                                        'type' => 'bold',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' link',
                                'marks' => [
                                    [
                                        'type' => 'link',
                                        'attrs' => [
                                            'href' => 'https://example.com',
                                            'uuid' => null,
                                            'anchor' => null,
                                            'target' => null,
                                            'linktype' => 'url',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'escaped characters as plain text' => [
                'markdown' => '\\*not bold\\* \\[not a link\\] \\<b> \\&amp; 1\\. end\\!',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => '*not bold* [not a link] <b> &amp; 1. end!',
                            ],
                        ],
                    ],
                ],
            ],
            'raw HTML and bare URLs as plain text' => [
                'markdown' => 'A <b>tag</b> and www.example.com',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'A <b>tag</b> and www.example.com',
                            ],
                        ],
                    ],
                ],
            ],
            'hard breaks' => [
                'markdown' => "First line\\\nSecond line",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'First line',
                            ],
                            [
                                'type' => 'hard_break',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Second line',
                            ],
                        ],
                    ],
                ],
            ],
            'soft line breaks as hard breaks' => [
                'markdown' => "First line\nSecond line",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'First line',
                            ],
                            [
                                'type' => 'hard_break',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Second line',
                            ],
                        ],
                    ],
                ],
            ],
            'headings' => [
                'markdown' => "# Title\n\n### Section with **bold** \\#",
                'content' => [
                    [
                        'type' => 'heading',
                        'attrs' => [
                            'level' => 1,
                        ],
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Title',
                            ],
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'attrs' => [
                            'level' => 3,
                        ],
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Section with ',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'bold',
                                'marks' => [
                                    [
                                        'type' => 'bold',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' #',
                            ],
                        ],
                    ],
                ],
            ],
            'a blockquote' => [
                'markdown' => "> Quoted\n>\n> - item",
                'content' => [
                    [
                        'type' => 'blockquote',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Quoted',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'bullet_list',
                                'content' => [
                                    [
                                        'type' => 'list_item',
                                        'content' => [
                                            [
                                                'type' => 'paragraph',
                                                'content' => [
                                                    [
                                                        'type' => 'text',
                                                        'text' => 'item',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'a nested bullet list' => [
                'markdown' => "- a\n  - b",
                'content' => [
                    [
                        'type' => 'bullet_list',
                        'content' => [
                            [
                                'type' => 'list_item',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'a',
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'bullet_list',
                                        'content' => [
                                            [
                                                'type' => 'list_item',
                                                'content' => [
                                                    [
                                                        'type' => 'paragraph',
                                                        'content' => [
                                                            [
                                                                'type' => 'text',
                                                                'text' => 'b',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'an ordered list with a start number' => [
                'markdown' => "3. a\n4. b",
                'content' => [
                    [
                        'type' => 'ordered_list',
                        'attrs' => [
                            'order' => 3,
                        ],
                        'content' => [
                            [
                                'type' => 'list_item',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'a',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'list_item',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'b',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'a loose list item with several paragraphs' => [
                'markdown' => "- first\n\n  second",
                'content' => [
                    [
                        'type' => 'bullet_list',
                        'content' => [
                            [
                                'type' => 'list_item',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'first',
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'second',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'code blocks' => [
                'markdown' => "```ts\nconst a = `<b>`;\n```\n\n```\n```",
                'content' => [
                    [
                        'type' => 'code_block',
                        'attrs' => [
                            'class' => 'language-ts',
                        ],
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'const a = `<b>`;',
                            ],
                        ],
                    ],
                    [
                        'type' => 'code_block',
                        'attrs' => [
                            'class' => null,
                        ],
                        'content' => [],
                    ],
                ],
            ],
            'a horizontal rule' => [
                'markdown' => "Above\n\n---\n\nBelow",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Above',
                            ],
                        ],
                    ],
                    [
                        'type' => 'horizontal_rule',
                    ],
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Below',
                            ],
                        ],
                    ],
                ],
            ],
            'an image, with its URL percent-encoded' => [
                'markdown' => '![An \\*alt\\*](<https://example.com/a b.png> "Title") caption',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'image',
                                'attrs' => [
                                    'id' => null,
                                    'src' => 'https://example.com/a%20b.png',
                                    'alt' => 'An *alt*',
                                    'title' => 'Title',
                                    'source' => null,
                                    'copyright' => null,
                                    'meta_data' => new \stdClass(),
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => ' caption',
                            ],
                        ],
                    ],
                ],
            ],
            'a line break before formatted text' => [
                'markdown' => "First line\n**Second line**",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'First line',
                            ],
                            [
                                'type' => 'hard_break',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Second line',
                                'marks' => [
                                    [
                                        'type' => 'bold',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'a task list as a bullet list' => [
                'markdown' => '- [x] Done',
                'content' => [
                    [
                        'type' => 'bullet_list',
                        'content' => [
                            [
                                'type' => 'list_item',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'Done',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'an indented code block' => [
                'markdown' => '    const a = 1;',
                'content' => [
                    [
                        'type' => 'code_block',
                        'attrs' => [
                            'class' => null,
                        ],
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'const a = 1;',
                            ],
                        ],
                    ],
                ],
            ],
            'an image without a title and with formatting in its description' => [
                'markdown' => "![An *alt*\\\ntext](https://example.com/image.png)",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'image',
                                'attrs' => [
                                    'id' => null,
                                    'src' => 'https://example.com/image.png',
                                    'alt' => 'An alt text',
                                    'title' => null,
                                    'source' => null,
                                    'copyright' => null,
                                    'meta_data' => new \stdClass(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'raw HTML blocks as plain text' => [
                'markdown' => "<pre>\nLorem\n\n</pre>",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => '<pre>',
                            ],
                            [
                                'type' => 'hard_break',
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Lorem',
                            ],
                            [
                                'type' => 'hard_break',
                            ],
                            [
                                'type' => 'hard_break',
                            ],
                            [
                                'type' => 'text',
                                'text' => '</pre>',
                            ],
                        ],
                    ],
                ],
            ],
            'table syntax as plain text' => [
                'markdown' => "| a | b |\n| - | - |",
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => '| a | b |',
                            ],
                            [
                                'type' => 'hard_break',
                            ],
                            [
                                'type' => 'text',
                                'text' => '| - | - |',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $content
     *
     * @throws CommonMarkException
     */
    #[DataProvider('getScenarios')]
    #[TestDox('Converts Markdown into a Storyblok rich text document.')]
    public function testConvertsMarkdown(string $markdown, array $content): void
    {
        $parser = new RichTextParser();

        self::assertEquals(
            [
                'type' => 'doc',
                'content' => $content,
            ],
            $parser->parse($markdown),
        );
    }
}
