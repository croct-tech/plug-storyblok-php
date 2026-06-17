<?php

declare(strict_types=1);

namespace Croct\Plug\Storyblok;

use Croct\Plug\FetchOptions;
use Croct\Plug\FetchResponse;
use Croct\Plug\Plug;
use Storyblok\Api\Domain\Value\Id;
use Storyblok\Api\Domain\Value\Uuid;
use Storyblok\Api\Request\StoriesRequest;
use Storyblok\Api\Request\StoryRequest;
use Storyblok\Api\Response\StoriesResponse;
use Storyblok\Api\Response\StoryResponse;
use Storyblok\Api\StoriesApiInterface as StoriesApi;

/**
 * Decorates the Storyblok Stories API to fetch content from Croct.
 *
 * Every fetched story is scanned for `croct` marker, and that node is replaced with the
 * content fetched from Croct after converting it to the Storyblok shape. On failure it falls
 * back to the original story content, so the page never breaks.
 */
final class CroctStoriesApi implements StoriesApi
{
    private StoriesApi $api;

    private Plug $plug;

    private ContentResolver $resolver;

    public function __construct(StoriesApi $inner, Plug $plug, ?ContentResolver $resolver = null)
    {
        $this->api = $inner;
        $this->plug = $plug;
        $this->resolver = $resolver ?? new ContentResolver();
    }

    public function bySlug(string $slug, ?StoryRequest $request = null): StoryResponse
    {
        return $this->resolveStory($this->api->bySlug($slug, $request), self::resolveLanguage($request));
    }

    public function byUuid(Uuid $uuid, ?StoryRequest $request = null): StoryResponse
    {
        return $this->resolveStory($this->api->byUuid($uuid, $request), self::resolveLanguage($request));
    }

    public function byId(Id $id, ?StoryRequest $request = null): StoryResponse
    {
        return $this->resolveStory($this->api->byId($id, $request), self::resolveLanguage($request));
    }

    public function all(?StoriesRequest $request = null): StoriesResponse
    {
        return $this->resolveStories($this->api->all($request), self::resolveLanguage($request));
    }

    public function allByContentType(string $contentType, ?StoriesRequest $request = null): StoriesResponse
    {
        return $this->resolveStories(
            $this->api->allByContentType($contentType, $request),
            self::resolveLanguage($request),
        );
    }

    /**
     * @param array<Uuid> $uuids
     */
    public function allByUuids(array $uuids, bool $keepOrder = true, ?StoriesRequest $request = null): StoriesResponse
    {
        return $this->resolveStories(
            $this->api->allByUuids($uuids, $keepOrder, $request),
            self::resolveLanguage($request),
        );
    }

    private function resolveStory(StoryResponse $response, string $language): StoryResponse
    {
        return new StoryResponse([
            'story' => $this->resolver->resolve($response->story, $this->createFetcher($language)),
            'cv' => $response->cv,
            'rels' => $response->rels,
            'rel_uuids' => $response->relUuids,
            'links' => $response->links,
        ]);
    }

    private function resolveStories(StoriesResponse $response, string $language): StoriesResponse
    {
        $fetcher = $this->createFetcher($language);

        $stories = [];

        foreach ($response->stories as $story) {
            $stories[] = $this->resolver->resolve($story, $fetcher);
        }

        return new StoriesResponse($response->total, $response->pagination, [
            'cv' => $response->cv,
            'rels' => $response->rels,
            'rel_uuids' => $response->relUuids,
            'links' => $response->links,
            'stories' => $stories,
        ]);
    }

    /**
     * Builds the slot fetcher for the given language, always requesting the content schema so the
     * conversion to the Storyblok shape can be schema-driven.
     *
     * @return callable(string): FetchResponse<array<string, mixed>, never, true>
     */
    private function createFetcher(string $language): callable
    {
        $options = FetchOptions::defaults()->withSchema();

        // Storyblok uses "default" to mean the space's default language.
        if ($language !== 'default') {
            $options = $options->withPreferredLocale($language);
        }

        return fn (string $slotId): FetchResponse => $this->plug->fetchContent($slotId, $options);
    }

    private static function resolveLanguage(StoryRequest|StoriesRequest|null $request): string
    {
        return $request === null ? 'default' : $request->language;
    }
}
