<?php

declare(strict_types=1);

namespace Croct\Plug\Storyblok\Tests;

use Croct\Plug\Content\SlotMetadata;
use Croct\Plug\Exception\ContentException;
use Croct\Plug\FetchResponse;
use Croct\Plug\Storyblok\ContentResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentResolver::class)]
#[TestDox('The content resolver')]
final class ContentResolverTest extends TestCase
{
    private const RANDOM_UUID = '00000000-0000-0000-0000-000000000000';

    /**
     * @return array<string, array{
     *     content: array<string, mixed>,
     *     schemas: array<string, mixed>,
     *     expected: array<string, mixed>,
     * }>
     */
    public static function getSupportedScenarios(): array
    {
        return [
            'a structure with different attribute types' => [
                'content' => [
                    '_component' => 'page',
                    'link_attribute' => 'http://example.com',
                    'text_attribute' => 'Lorem ipsum',
                    'number_attribute' => 9,
                    'boolean_attribute' => false,
                    'markdown_attribute' => 'Lorem *ipsum*',
                    'date_time_attribute' => '2026-01-01 01:02',
                    'text_area_attribute' => 'Lorem ipsum',
                    'multi_assets_attribute' => [
                        'https://example.com/asset-1.png',
                        'https://example.com/asset-2.png',
                    ],
                    'asset_attribute' => 'https://www.example.com/image.png',
                    'multi_option_attribute' => [
                        'a',
                        'b',
                    ],
                    'single_option_attribute' => 'b',
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                        'title' => 'Block with all types',
                        'attributes' => [
                            'link_attribute' => [
                                'type' => [
                                    'type' => 'text',
                                    'format' => 'url',
                                ],
                            ],
                            'text_attribute' => [
                                'type' => [
                                    'type' => 'text',
                                ],
                            ],
                            'number_attribute' => [
                                'type' => [
                                    'type' => 'number',
                                ],
                            ],
                            'boolean_attribute' => [
                                'type' => [
                                    'type' => 'boolean',
                                ],
                            ],
                            'markdown_attribute' => [
                                'type' => [
                                    'type' => 'text',
                                ],
                            ],
                            'date_time_attribute' => [
                                'type' => [
                                    'type' => 'text',
                                ],
                            ],
                            'text_area_attribute' => [
                                'type' => [
                                    'type' => 'text',
                                ],
                            ],
                            'multi_assets_attribute' => [
                                'type' => [
                                    'type' => 'list',
                                    'items' => [
                                        'type' => 'reference',
                                        'id' => '@croct/file',
                                    ],
                                ],
                            ],
                            'asset_attribute' => [
                                'type' => [
                                    'type' => 'reference',
                                    'id' => '@croct/file',
                                ],
                            ],
                            'multi_option_attribute' => [
                                'type' => [
                                    'type' => 'list',
                                    'items' => [
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                            'single_option_attribute' => [
                                'type' => [
                                    'type' => 'text',
                                ],
                            ],
                        ],
                    ],
                    'definitions' => [],
                ],
                'expected' => [
                    '_uid' => self::RANDOM_UUID,
                    'component' => 'page',
                    'link_attribute' => self::createMultilink('http://example.com'),
                    'text_attribute' => 'Lorem ipsum',
                    'asset_attribute' => self::createAsset('https://www.example.com/image.png'),
                    'number_attribute' => '9',
                    'boolean_attribute' => false,
                    'markdown_attribute' => 'Lorem *ipsum*',
                    'date_time_attribute' => '2026-01-01 01:02',
                    'text_area_attribute' => 'Lorem ipsum',
                    'multi_assets_attribute' => [
                        self::createAsset('https://example.com/asset-1.png'),
                        self::createAsset('https://example.com/asset-2.png'),
                    ],
                    'multi_option_attribute' => [
                        'a',
                        'b',
                    ],
                    'single_option_attribute' => 'b',
                ],
            ],
            'a structure with a list of text items' => [
                'content' => [
                    '_component' => 'tags',
                    'items' => [
                        'javascript',
                        'typescript',
                        'react',
                    ],
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                        'attributes' => [
                            'items' => [
                                'type' => [
                                    'type' => 'list',
                                    'items' => [
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'definitions' => [],
                ],
                'expected' => [
                    '_uid' => self::RANDOM_UUID,
                    'component' => 'tags',
                    'items' => [
                        'javascript',
                        'typescript',
                        'react',
                    ],
                ],
            ],
            'a structure with a list of nested structures' => [
                'content' => [
                    '_component' => 'cardList',
                    'cards' => [
                        [
                            '_component' => 'card',
                            'title' => 'Card 1',
                        ],
                        [
                            '_component' => 'card',
                            'title' => 'Card 2',
                        ],
                    ],
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                        'attributes' => [
                            'cards' => [
                                'type' => [
                                    'type' => 'list',
                                    'items' => [
                                        'type' => 'structure',
                                        'attributes' => [
                                            'title' => [
                                                'type' => [
                                                    'type' => 'text',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'definitions' => [],
                ],
                'expected' => [
                    '_uid' => self::RANDOM_UUID,
                    'component' => 'cardList',
                    'cards' => [
                        [
                            '_uid' => self::RANDOM_UUID,
                            'component' => 'card',
                            'title' => 'Card 1',
                        ],
                        [
                            '_uid' => self::RANDOM_UUID,
                            'component' => 'card',
                            'title' => 'Card 2',
                        ],
                    ],
                ],
            ],
            'a structure with a nested structure attribute' => [
                'content' => [
                    '_component' => 'article',
                    'header' => [
                        '_component' => 'header',
                        'title' => 'Article Title',
                        'columns' => 2,
                    ],
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                        'attributes' => [
                            'header' => [
                                'type' => [
                                    'type' => 'structure',
                                    'attributes' => [
                                        'title' => [
                                            'type' => [
                                                'type' => 'text',
                                            ],
                                        ],
                                        'columns' => [
                                            'type' => [
                                                'type' => 'number',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'definitions' => [],
                ],
                'expected' => [
                    '_uid' => self::RANDOM_UUID,
                    'component' => 'article',
                    'header' => [
                        '_uid' => self::RANDOM_UUID,
                        'component' => 'header',
                        'title' => 'Article Title',
                        'columns' => '2',
                    ],
                ],
            ],
            'a structure with a union attribute' => [
                'content' => [
                    '_component' => 'section',
                    'media' => [
                        '_type' => 'image',
                        'src' => 'https://example.com/photo.jpg',
                    ],
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                        'attributes' => [
                            'media' => [
                                'type' => [
                                    'type' => 'union',
                                    'types' => [
                                        'image' => [
                                            'type' => 'structure',
                                            'attributes' => [
                                                'src' => [
                                                    'type' => [
                                                        'type' => 'text',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'video' => [
                                            'type' => 'structure',
                                            'attributes' => [
                                                'url' => [
                                                    'type' => [
                                                        'type' => 'text',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'definitions' => [],
                ],
                'expected' => [
                    '_uid' => self::RANDOM_UUID,
                    'component' => 'section',
                    'media' => [
                        '_uid' => self::RANDOM_UUID,
                        'component' => 'image',
                        'src' => 'https://example.com/photo.jpg',
                    ],
                ],
            ],
            'a structure with a reference to a definition' => [
                'content' => [
                    '_component' => 'page',
                    'button' => [
                        'label' => 'Submit',
                        'url' => 'https://example.com/submit',
                    ],
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                        'attributes' => [
                            'button' => [
                                'type' => [
                                    'type' => 'reference',
                                    'id' => 'button-component',
                                ],
                            ],
                        ],
                    ],
                    'definitions' => [
                        'button-component' => [
                            'type' => 'structure',
                            'attributes' => [
                                'label' => [
                                    'type' => [
                                        'type' => 'text',
                                    ],
                                ],
                                'url' => [
                                    'type' => [
                                        'type' => 'text',
                                        'format' => 'url',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    '_uid' => self::RANDOM_UUID,
                    'component' => 'page',
                    'button' => [
                        '_uid' => self::RANDOM_UUID,
                        'component' => 'button-component',
                        'label' => 'Submit',
                        'url' => self::createMultilink('https://example.com/submit'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $schemas
     * @param array<string, mixed> $expected
     */
    #[DataProvider('getSupportedScenarios')]
    #[TestDox('Converts Croct content to the Storyblok shape.')]
    public function testConvertsContent(array $content, array $schemas, array $expected): void
    {
        self::assertEquals($expected, self::createResolver()->create($content, $schemas));
    }

    /**
     * @return array<string, array{content: array<string, mixed>, schemas: array<string, mixed>|null}>
     */
    public static function getUnsupportedScenarios(): array
    {
        $text = [
            'type' => 'text',
        ];
        $number = [
            'type' => 'number',
        ];
        $boolean = [
            'type' => 'boolean',
        ];
        $list = [
            'type' => 'list',
            'items' => [
                'type' => 'text',
            ],
        ];
        $structure = [
            'type' => 'structure',
            'attributes' => [],
        ];

        $scenarios = [
            'no schema' => [
                'content' => [
                    '_component' => 'page',
                    'title' => 'Hello',
                ],
                'schemas' => null,
            ],
            'a non-string component' => [
                'content' => [
                    '_component' => 123,
                    'title' => 'Hello',
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'text',
                    ],
                    'definitions' => [],
                ],
            ],
            'a missing component' => [
                'content' => [
                    'title' => 'Hello',
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'text',
                    ],
                    'definitions' => [],
                ],
            ],
            'an empty component' => [
                'content' => [
                    '_component' => '',
                    'title' => 'Hello',
                ],
                'schemas' => self::createStructureSchema('title', $text),
            ],
            'a whitespace-only component' => [
                'content' => [
                    '_component' => '   ',
                    'title' => 'Hello',
                ],
                'schemas' => self::createStructureSchema('title', $text),
            ],
            'a component that is empty after stripping the version' => [
                'content' => [
                    '_component' => '@1.0.0',
                    'title' => 'Hello',
                ],
                'schemas' => self::createStructureSchema('title', $text),
            ],
            'an attribute not in the schema' => [
                'content' => [
                    '_component' => 'page',
                    'title' => 'Hello',
                    'unknownAttribute' => 'value',
                ],
                'schemas' => self::createStructureSchema('title', $text),
            ],
            'an attribute with a non-array type' => [
                'content' => [
                    '_component' => 'page',
                    'title' => 'Hello',
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                        'attributes' => [
                            'title' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                    'definitions' => [],
                ],
            ],
            'a union member not in the definition' => [
                'content' => [
                    '_component' => 'section',
                    'media' => [
                        '_type' => 'audio',
                        'src' => 'https://example.com/sound.mp3',
                    ],
                ],
                'schemas' => self::createStructureSchema('media', [
                    'type' => 'union',
                    'types' => [],
                ]),
            ],
            'a union member that is not a string' => [
                'content' => [
                    '_component' => 'section',
                    'media' => [
                        'src' => 'https://example.com/photo.jpg',
                    ],
                ],
                'schemas' => self::createStructureSchema('media', [
                    'type' => 'union',
                    'types' => [
                        'image' => [
                            'type' => 'structure',
                            'attributes' => [],
                        ],
                    ],
                ]),
            ],
            'a list item that cannot be resolved' => [
                'content' => [
                    '_component' => 'cardList',
                    'cards' => [
                        [
                            'title' => 'Valid Card',
                        ],
                        [
                            'title' => 'Invalid Card',
                        ],
                    ],
                ],
                'schemas' => self::createStructureSchema('cards', [
                    'type' => 'list',
                    'items' => [
                        'type' => 'reference',
                        'id' => 'missing-definition',
                    ],
                ]),
            ],
            'a nested attribute that cannot be resolved' => [
                'content' => [
                    '_component' => 'page',
                    'header' => [
                        'title' => 'Title',
                    ],
                ],
                'schemas' => self::createStructureSchema('header', [
                    'type' => 'reference',
                    'id' => 'missing-definition',
                ]),
            ],
            'a reference without an id' => [
                'content' => [
                    '_component' => 'page',
                    'button' => [
                        'label' => 'Submit',
                    ],
                ],
                'schemas' => self::createStructureSchema('button', [
                    'type' => 'reference',
                ]),
            ],
            'a non-array root' => [
                'content' => [
                    '_component' => 'page',
                ],
                'schemas' => [
                    'root' => 'text',
                    'definitions' => [],
                ],
            ],
            'a structure definition without attributes' => [
                'content' => [
                    '_component' => 'page',
                    'title' => 'Hi',
                ],
                'schemas' => [
                    'root' => [
                        'type' => 'structure',
                    ],
                    'definitions' => [],
                ],
            ],
            'a list attribute without an item definition' => [
                'content' => [
                    '_component' => 'page',
                    'items' => [
                        'a',
                    ],
                ],
                'schemas' => self::createStructureSchema('items', [
                    'type' => 'list',
                ]),
            ],
        ];

        $mismatches = [
            'a number where text is expected' => [
                'title',
                42,
                $text,
            ],
            'a number where a boolean is expected' => [
                'flag',
                1,
                $boolean,
            ],
            'a boolean where text is expected' => [
                'title',
                true,
                $text,
            ],
            'a boolean where a number is expected' => [
                'count',
                false,
                $number,
            ],
            'a string where a number is expected' => [
                'count',
                'not a number',
                $number,
            ],
            'a string where a boolean is expected' => [
                'flag',
                'true',
                $boolean,
            ],
            'a string where a list is expected' => [
                'items',
                'not an array',
                $list,
            ],
            'a string where a structure is expected' => [
                'header',
                'not an object',
                $structure,
            ],
            'an array where text is expected' => [
                'title',
                [
                    'a',
                    'b',
                ],
                $text,
            ],
            'an array where a number is expected' => [
                'count',
                [
                    1,
                    2,
                    3,
                ],
                $number,
            ],
            'an array where a structure is expected' => [
                'header',
                [
                    'a',
                    'b',
                ],
                $structure,
            ],
            'an object where text is expected' => [
                'title',
                [
                    'nested' => 'value',
                ],
                $text,
            ],
            'an object where a number is expected' => [
                'count',
                [
                    'nested' => 'value',
                ],
                $number,
            ],
            'an object where a boolean is expected' => [
                'flag',
                [
                    'nested' => 'value',
                ],
                $boolean,
            ],
            'an object where a list is expected' => [
                'items',
                [
                    'nested' => 'value',
                ],
                $list,
            ],
            'null where text is expected' => [
                'title',
                null,
                $text,
            ],
            'null where a number is expected' => [
                'count',
                null,
                $number,
            ],
            'null where a boolean is expected' => [
                'flag',
                null,
                $boolean,
            ],
            'null where a list is expected' => [
                'items',
                null,
                $list,
            ],
            'null where a structure is expected' => [
                'header',
                null,
                $structure,
            ],
        ];

        foreach ($mismatches as $name => [$attribute, $value, $type]) {
            $scenarios[$name] = [
                'content' => [
                    '_component' => 'page',
                    $attribute => $value,
                ],
                'schemas' => self::createStructureSchema($attribute, $type),
            ];
        }

        return $scenarios;
    }

    /**
     * @param array<string, mixed>      $content
     * @param array<string, mixed>|null $schemas
     */
    #[DataProvider('getUnsupportedScenarios')]
    #[TestDox('Returns null for content that cannot be converted.')]
    public function testReturnsNullForUnsupportedContent(array $content, ?array $schemas): void
    {
        self::assertNull(self::createResolver()->create($content, $schemas));
    }

    #[TestDox('Returns scalar content as-is without fetching.')]
    public function testReturnsScalarContentAsIs(): void
    {
        $fetcher = static fn (string $id): ?FetchResponse => self::fail(\sprintf('Unexpected fetch of "%s".', $id));

        $resolver = self::createResolver();

        self::assertSame('hello', $resolver->resolve('hello', $fetcher));
        self::assertSame(42, $resolver->resolve(42, $fetcher));
        self::assertTrue($resolver->resolve(true, $fetcher));
        self::assertNull($resolver->resolve(null, $fetcher));
    }

    /**
     * @return array<string, array{content: array<string, mixed>}>
     */
    public static function getInvalidMarkerScenarios(): array
    {
        return [
            'the marker is an empty string' => [
                'content' => [
                    'croct' => '',
                    'other' => 'value',
                ],
            ],
            'the marker is whitespace only' => [
                'content' => [
                    'croct' => '   ',
                    'other' => 'value',
                ],
            ],
            'the marker is not a string' => [
                'content' => [
                    'croct' => 123,
                    'other' => 'value',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $content
     */
    #[DataProvider('getInvalidMarkerScenarios')]
    #[TestDox('Does not fetch for an invalid marker.')]
    public function testDoesNotFetchForInvalidMarker(array $content): void
    {
        $fetcher = static fn (string $id): ?FetchResponse => self::fail(\sprintf('Unexpected fetch of "%s".', $id));

        self::assertEquals($content, self::createResolver()->resolve($content, $fetcher));
    }

    #[TestDox('Fetches and converts the content of a marker.')]
    public function testFetchesAndConvertsMarker(): void
    {
        $calls = [];
        $fetcher = static function (string $id) use (&$calls): FetchResponse {
            $calls[] = $id;

            return self::createResponse(
                [
                    '_component' => 'hero',
                    'title' => 'Fetched Title',
                ],
                self::createStructureSchema('title', [
                    'type' => 'text',
                ]),
            );
        };

        self::assertEquals(
            [
                '_uid' => self::RANDOM_UUID,
                'component' => 'hero',
                'title' => 'Fetched Title',
            ],
            self::createResolver()->resolve(
                [
                    'croct' => 'slot-id',
                ],
                $fetcher,
            ),
        );
        self::assertSame(['slot-id'], $calls);
    }

    #[TestDox('Resolves markers at any depth.')]
    public function testResolvesMarkersAtAnyDepth(): void
    {
        $fetcher = static fn (string $id): FetchResponse => self::createResponse(
            [
                '_component' => 'widget',
                'data' => 'fetched',
            ],
            self::createStructureSchema('data', [
                'type' => 'text',
            ]),
        );

        $content = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'croct' => 'deep-slot',
                    ],
                ],
            ],
        ];

        self::assertEquals(
            [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            '_uid' => self::RANDOM_UUID,
                            'component' => 'widget',
                            'data' => 'fetched',
                        ],
                    ],
                ],
            ],
            self::createResolver()->resolve($content, $fetcher),
        );
    }

    #[TestDox('Resolves markers nested in arrays.')]
    public function testResolvesMarkersInArrays(): void
    {
        $calls = [];
        $fetcher = static function (string $id) use (&$calls): FetchResponse {
            $calls[] = $id;

            return self::createResponse(
                [
                    '_component' => 'card',
                    'title' => 'Fetched',
                ],
                self::createStructureSchema('title', [
                    'type' => 'text',
                ]),
            );
        };

        $card = [
            '_uid' => self::RANDOM_UUID,
            'component' => 'card',
            'title' => 'Fetched',
        ];

        self::assertEquals(
            [
                $card,
                $card,
            ],
            self::createResolver()->resolve(
                [
                    [
                        'croct' => 'slot-1',
                    ],
                    [
                        'croct' => 'slot-2',
                    ],
                ],
                $fetcher,
            ),
        );
        self::assertSame(['slot-1', 'slot-2'], $calls);
    }

    #[TestDox('Resolves only the outermost marker.')]
    public function testResolvesOnlyOutermostMarker(): void
    {
        $calls = [];
        $fetcher = static function (string $id) use (&$calls): FetchResponse {
            $calls[] = $id;

            return self::createResponse(
                [
                    '_component' => 'outer@1',
                ],
                [
                    'root' => [
                        'type' => 'structure',
                        'attributes' => [],
                    ],
                    'definitions' => [],
                ],
            );
        };

        $content = [
            'croct' => 'outer-slot',
            'nested' => [
                'croct' => 'inner-slot',
            ],
        ];

        self::assertEquals(
            [
                '_uid' => self::RANDOM_UUID,
                'component' => 'outer',
            ],
            self::createResolver()->resolve($content, $fetcher),
        );
        self::assertSame(['outer-slot'], $calls);
    }

    #[TestDox('Falls back to the marker content when the fetch fails.')]
    public function testFallsBackWhenFetchFails(): void
    {
        $fetcher = static fn (string $id): FetchResponse => throw new ContentException('Fetch failed.');

        $content = [
            'croct' => 'slot-id',
            'fallbackTitle' => 'Fallback',
            'fallbackData' => 123,
        ];

        self::assertEquals(
            [
                'fallbackTitle' => 'Fallback',
                'fallbackData' => 123,
            ],
            self::createResolver()->resolve($content, $fetcher),
        );
    }

    #[TestDox('Falls back to the marker content when the conversion fails.')]
    public function testFallsBackWhenConversionFails(): void
    {
        $fetcher = static fn (string $id): FetchResponse => self::createResponse(
            [
                '_component' => 'widget',
                'data' => 'fetched',
            ],
            null,
        );

        $content = [
            'croct' => 'slot-id',
            'fallbackTitle' => 'Fallback',
            'fallbackData' => 123,
        ];

        self::assertEquals(
            [
                'fallbackTitle' => 'Fallback',
                'fallbackData' => 123,
            ],
            self::createResolver()->resolve($content, $fetcher),
        );
    }

    #[TestDox('Falls back to the marker content when the fetcher returns nothing.')]
    public function testFallsBackWhenFetcherReturnsNothing(): void
    {
        $fetcher = static fn (string $id): ?FetchResponse => null;

        $content = [
            'croct' => 'slot-id',
            'fallbackTitle' => 'Fallback',
            'fallbackData' => 123,
        ];

        self::assertEquals(
            [
                'fallbackTitle' => 'Fallback',
                'fallbackData' => 123,
            ],
            self::createResolver()->resolve($content, $fetcher),
        );
    }

    private static function createResolver(): ContentResolver
    {
        return new ContentResolver(static fn (): string => self::RANDOM_UUID);
    }

    /**
     * @param array<string, mixed>|null $schema
     */
    private static function createResponse(mixed $content, ?array $schema): FetchResponse
    {
        return new FetchResponse($content, new SlotMetadata(schema: $schema));
    }

    /**
     * @param array<string, mixed> $type
     *
     * @return array{root: array<string, mixed>, definitions: array<string, mixed>}
     */
    private static function createStructureSchema(string $attribute, array $type): array
    {
        return [
            'root' => [
                'type' => 'structure',
                'attributes' => [
                    $attribute => [
                        'type' => $type,
                    ],
                ],
            ],
            'definitions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function createMultilink(string $url): array
    {
        return [
            'id' => '',
            'linktype' => 'url',
            'fieldtype' => 'multilink',
            'url' => $url,
            'cached_url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function createAsset(string $filename): array
    {
        return [
            'id' => null,
            'alt' => null,
            'name' => '',
            'focus' => '',
            'title' => null,
            'filename' => $filename,
            'copyright' => null,
            'fieldtype' => 'asset',
            'meta_data' => new \stdClass(),
            'is_external_url' => true,
        ];
    }
}
