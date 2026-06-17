<?php

declare(strict_types=1);

namespace Croct\Plug\Storyblok\Tests;

use Croct\Plug\Content\ContentSource;
use Croct\Plug\Content\SlotMetadata;
use Croct\Plug\Exception\ContentException;
use Croct\Plug\FetchOptions;
use Croct\Plug\FetchResponse;
use Croct\Plug\Plug;
use Croct\Plug\Storyblok\ContentResolver;
use Croct\Plug\Storyblok\CroctStoriesApi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Domain\Value\Id;
use Storyblok\Api\Domain\Value\Total;
use Storyblok\Api\Domain\Value\Uuid;
use Storyblok\Api\Request\StoryRequest;
use Storyblok\Api\Response\StoriesResponse;
use Storyblok\Api\Response\StoryResponse;
use Storyblok\Api\StoriesApiInterface as StoriesApi;

#[CoversClass(CroctStoriesApi::class)]
#[TestDox('The Croct Storyblok decorator')]
final class CroctStoriesApiTest extends TestCase
{
    private const UID = '00000000-0000-0000-0000-000000000000';

    private const UUID = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Leaves stories without a marker unchanged and never calls Croct.')]
    public function testLeavesUnmarkedStoriesUnchanged(): void
    {
        $story = self::unmarkedStory();

        $api = $this->createMock(StoriesApi::class);
        $api->method('bySlug')->willReturn(self::createStoryResponse($story));

        $plug = $this->createMock(Plug::class);
        $plug->expects(self::never())->method('fetchContent');

        self::assertSame($story, $this->createApi($api, $plug)->bySlug('home')->story);
    }

    #[TestDox('Resolves a marker in the story through the content schema.')]
    public function testResolvesMarkerWithSchema(): void
    {
        $api = $this->createMock(StoriesApi::class);
        $api->method('bySlug')->willReturn(self::createStoryResponse(self::markedStory()));

        $options = null;

        $plug = $this->createMock(Plug::class);
        $plug->method('fetchContent')->willReturnCallback(
            static function (string $slotId, ?FetchOptions $fetchOptions) use (&$options): FetchResponse {
                $options = $fetchOptions;

                return self::createBannerResponse('Welcome');
            },
        );

        $result = $this->createApi($api, $plug)->bySlug('home');

        self::assertEquals(
            [
                'body' => [
                    [
                        '_uid' => self::UID,
                        'component' => 'banner',
                        'headline' => 'Welcome',
                    ],
                ],
            ],
            $result->story,
        );

        self::assertTrue($options?->includesSchema());
    }

    #[TestDox('Keeps the block content when the Croct fetch fails.')]
    public function testFallsBackOnFailure(): void
    {
        $api = $this->createMock(StoriesApi::class);
        $api->method('bySlug')->willReturn(self::createStoryResponse(self::markedStory()));

        $plug = $this->createMock(Plug::class);
        $plug->method('fetchContent')->willThrowException(new ContentException('boom'));

        $result = $this->createApi($api, $plug)->bySlug('home');

        self::assertEquals(
            [
                'body' => [
                    [],
                ],
            ],
            $result->story,
        );
    }

    #[TestDox('Resolves a story fetched by UUID.')]
    public function testResolvesByUuid(): void
    {
        $story = self::unmarkedStory();

        $api = $this->createMock(StoriesApi::class);
        $api->method('byUuid')->willReturn(self::createStoryResponse($story));

        self::assertSame(
            $story,
            $this->createApi($api, $this->unusedPlug())->byUuid(new Uuid(self::UUID))->story,
        );
    }

    #[TestDox('Resolves a story fetched by ID.')]
    public function testResolvesById(): void
    {
        $story = self::unmarkedStory();

        $api = $this->createMock(StoriesApi::class);
        $api->method('byId')->willReturn(self::createStoryResponse($story));

        self::assertSame(
            $story,
            $this->createApi($api, $this->unusedPlug())->byId(new Id(1))->story,
        );
    }

    #[TestDox('Resolves every story when listing all stories.')]
    public function testResolvesAllStories(): void
    {
        $story = self::unmarkedStory();

        $api = $this->createMock(StoriesApi::class);
        $api->method('all')->willReturn(self::createStoriesResponse([$story]));

        self::assertEquals(
            [$story],
            $this->createApi($api, $this->unusedPlug())->all()->stories,
        );
    }

    #[TestDox('Resolves every story when listing by content type.')]
    public function testResolvesAllStoriesByContentType(): void
    {
        $story = self::unmarkedStory();

        $api = $this->createMock(StoriesApi::class);
        $api->method('allByContentType')->willReturn(self::createStoriesResponse([$story]));

        self::assertEquals(
            [$story],
            $this->createApi($api, $this->unusedPlug())->allByContentType('page')->stories,
        );
    }

    #[TestDox('Resolves every story when listing by UUIDs.')]
    public function testResolvesAllStoriesByUuids(): void
    {
        $story = self::unmarkedStory();

        $api = $this->createMock(StoriesApi::class);
        $api->method('allByUuids')->willReturn(self::createStoriesResponse([$story]));

        self::assertEquals(
            [$story],
            $this->createApi($api, $this->unusedPlug())->allByUuids([new Uuid(self::UUID)])->stories,
        );
    }

    #[TestDox('Forwards the request language as the preferred locale.')]
    public function testForwardsThePreferredLocale(): void
    {
        $api = $this->createMock(StoriesApi::class);
        $api->method('bySlug')->willReturn(self::createStoryResponse(self::markedStory()));

        $options = null;

        $plug = $this->createMock(Plug::class);
        $plug->method('fetchContent')->willReturnCallback(
            static function (string $slotId, ?FetchOptions $fetchOptions) use (&$options): FetchResponse {
                $options = $fetchOptions;

                return self::createBannerResponse('Hallo');
            },
        );

        $this->createApi($api, $plug)->bySlug('home', new StoryRequest('de-de'));

        self::assertSame('de-de', $options?->getPreferredLocale());
    }

    private function createApi(StoriesApi $api, Plug $plug): CroctStoriesApi
    {
        return new CroctStoriesApi($api, $plug, new ContentResolver(static fn (): string => self::UID));
    }

    private function unusedPlug(): Plug
    {
        $plug = $this->createMock(Plug::class);
        $plug->expects(self::never())->method('fetchContent');

        return $plug;
    }

    /**
     * @return array<string, mixed>
     */
    private static function unmarkedStory(): array
    {
        return [
            'component' => 'page',
            'body' => [
                [
                    'component' => 'banner',
                    'headline' => 'Hi',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function markedStory(): array
    {
        return [
            'body' => [
                [
                    'croct' => 'home-banner',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $story
     */
    private static function createStoryResponse(array $story): StoryResponse
    {
        return new StoryResponse([
            'story' => $story,
            'cv' => 1,
            'rels' => [],
            'rel_uuids' => [],
            'links' => [],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $stories
     */
    private static function createStoriesResponse(array $stories): StoriesResponse
    {
        return new StoriesResponse(
            new Total(\count($stories)),
            new Pagination(),
            [
                'stories' => $stories,
                'cv' => 1,
                'rels' => [],
                'rel_uuids' => [],
                'links' => [],
            ],
        );
    }

    /**
     * @return FetchResponse<array<string, mixed>, mixed, bool>
     */
    private static function createBannerResponse(string $headline): FetchResponse
    {
        return new FetchResponse(
            [
                '_component' => 'banner',
                'headline' => $headline,
            ],
            new SlotMetadata(version: '1', contentSource: ContentSource::SLOT, schema: [
                'root' => [
                    'type' => 'structure',
                    'attributes' => [
                        'headline' => [
                            'type' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                ],
                'definitions' => [],
            ]),
        );
    }
}
