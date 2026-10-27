<?php

declare(strict_types=1);

namespace Croct\Plug\Storyblok;

use Croct\Plug\Exception\CroctException;
use Croct\Plug\FetchResponse;
use Croct\Plug\Uuid;
use League\CommonMark\Exception\CommonMarkException;

/**
 * Converts Croct content to the Storyblok shape.
 *
 * When no schema is available, or the content does not match it, conversion yields `null` so the
 * caller can fall back to the original content.
 */
final class ContentResolver
{
    /** @var callable(): string */
    private $uidGenerator;

    private RichTextParser $richTextParser;

    /**
     * @param (callable(): string)|null $uidGenerator Generates the `_uid` of each structure.
     */
    public function __construct(?callable $uidGenerator = null)
    {
        $this->uidGenerator = $uidGenerator ?? static fn (): string => Uuid::random()->toString();
        $this->richTextParser = new RichTextParser();
    }

    /**
     * Walks the content tree, replacing every `croct` marker with the fetched, converted content.
     *
     * @param callable(string): ?FetchResponse<mixed, mixed, bool> $fetcher
     *        Fetches the content of a slot by ID.
     * @param array<array-key, mixed>                              $relatedStories
     *        The stories the content may relate to, such as the `rels` of a Storyblok response.
     */
    public function resolve(mixed $content, callable $fetcher, array $relatedStories = []): mixed
    {
        return $this->resolveContent($content, $fetcher, self::collectStories([$content, $relatedStories]));
    }

    /**
     * @param callable(string): ?FetchResponse<mixed, mixed, bool> $fetcher
     * @param array<string, array<array-key, mixed>>               $stories
     */
    private function resolveContent(mixed $content, callable $fetcher, array $stories): mixed
    {
        if (!\is_array($content)) {
            return $content;
        }

        $marker = $content['croct'] ?? null;

        if (\is_string($marker) && \trim($marker) !== '') {
            $rest = $content;

            unset($rest['croct']);

            try {
                $response = $fetcher($marker);
            } catch (CroctException) {
                // Fail open: keep the marker's own content as the fallback.
                return $rest;
            }

            if (!$response instanceof FetchResponse) {
                return $rest;
            }

            return $this->create(
                $response->getContent(),
                $response->getMetadata()?->getSchema(),
                $rest,
                $stories,
            ) ?? $rest;
        }

        $result = [];

        foreach ($content as $key => $value) {
            $result[$key] = $this->resolveContent($value, $fetcher, $stories);
        }

        return $result;
    }

    /**
     * Converts Croct content to the Storyblok shape using the content schema.
     *
     * @param array<array-key, mixed>|null           $schema
     * @param mixed                                  $original The original Storyblok content, which tells
     *                                                         links and story relations apart from text.
     * @param array<string, array<array-key, mixed>> $stories  The stories available for story relations.
     *
     * @return array<array-key, mixed>|null The Storyblok content, or null if not convertible.
     */
    private function create(mixed $content, ?array $schema, mixed $original, array $stories): ?array
    {
        if ($schema === null) {
            return null;
        }

        $root = $schema['root'] ?? null;

        if (!\is_array($root)) {
            return null;
        }

        $result = $this->convert($content, $schema, $root, $stories, $original);

        return \is_array($result) ? $result : null;
    }

    /**
     * @param array<array-key, mixed>                $schemas
     * @param array<array-key, mixed>                $definition
     * @param array<string, array<array-key, mixed>> $stories
     */
    private function convert(mixed $content, array $schemas, array $definition, array $stories, mixed $original): mixed
    {
        if (\is_int($content) || \is_float($content)) {
            return self::convertNumber($content, $definition);
        }

        if (\is_bool($content)) {
            return self::convertBoolean($content, $definition);
        }

        if (\is_string($content)) {
            return $this->convertString($content, $definition, $stories, $original);
        }

        if (\is_array($content)) {
            return \array_is_list($content)
                ? $this->convertArray($content, $schemas, $definition, $stories, $original)
                : $this->convertObject($content, $schemas, $definition, $stories, $original);
        }

        return null;
    }

    /**
     * @param list<mixed>                            $content
     * @param array<array-key, mixed>                $schemas
     * @param array<array-key, mixed>                $definition
     * @param array<string, array<array-key, mixed>> $stories
     *
     * @return list<mixed>|null
     */
    private function convertArray(
        array $content,
        array $schemas,
        array $definition,
        array $stories,
        mixed $original,
    ): ?array {
        if (($definition['type'] ?? null) !== 'list') {
            return null;
        }

        $items = $definition['items'] ?? null;

        if (!\is_array($items)) {
            return null;
        }

        // A related story in the original content indicates that the items are story relations,
        // which may be reordered, so every item is matched against the same related story.
        $relatedStory = null;

        foreach (\is_array($original) ? $original : [] as $originalItem) {
            if (self::isStory($originalItem)) {
                $relatedStory = $originalItem;

                break;
            }
        }

        $elements = [];

        foreach ($content as $index => $item) {
            $element = $this->convert(
                $item,
                $schemas,
                $items,
                $stories,
                $relatedStory ?? (\is_array($original) ? $original[$index] ?? null : null),
            );

            if ($element === null) {
                return null;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @param array<array-key, mixed>                $content
     * @param array<array-key, mixed>                $schemas
     * @param array<array-key, mixed>                $definition
     * @param array<string, array<array-key, mixed>> $stories
     */
    private function convertObject(
        array $content,
        array $schemas,
        array $definition,
        array $stories,
        mixed $original,
    ): mixed {
        return match ($definition['type'] ?? null) {
            'structure' => $this->convertStructure($content, $schemas, $definition, $stories, $original),
            'union' => $this->convertUnion($content, $schemas, $definition, $stories, $original),
            'reference' => $this->convertReference($content, $schemas, $definition, $stories, $original),
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed>                $content
     * @param array<array-key, mixed>                $schemas
     * @param array<array-key, mixed>                $definition
     * @param array<string, array<array-key, mixed>> $stories
     *
     * @return array<array-key, mixed>|null
     */
    private function convertStructure(
        array $content,
        array $schemas,
        array $definition,
        array $stories,
        mixed $original,
    ): ?array {
        $component = $content['_component'] ?? null;
        $componentName = \is_string($component) && \trim($component) !== ''
            ? self::getComponentName($component)
            : null;

        if ($componentName === null) {
            return null;
        }

        $attributes = $definition['attributes'] ?? null;

        if (!\is_array($attributes)) {
            return null;
        }

        $entries = [];

        foreach ($content as $key => $value) {
            if ($key === '_component' || $key === '_type') {
                continue;
            }

            $attribute = $attributes[$key] ?? null;

            if (!\is_array($attribute)) {
                return null;
            }

            $type = $attribute['type'] ?? null;

            if (!\is_array($type)) {
                return null;
            }

            $element = $this->convert(
                $value,
                $schemas,
                $type,
                $stories,
                \is_array($original) ? $original[$key] ?? null : null,
            );

            if ($element === null) {
                return null;
            }

            $entries[$key] = $element;
        }

        return [
            '_uid' => ($this->uidGenerator)(),
            'component' => $componentName,
            ...$entries,
        ];
    }

    /**
     * @param array<array-key, mixed>                $content
     * @param array<array-key, mixed>                $schemas
     * @param array<array-key, mixed>                $definition
     * @param array<string, array<array-key, mixed>> $stories
     */
    private function convertUnion(
        array $content,
        array $schemas,
        array $definition,
        array $stories,
        mixed $original,
    ): mixed {
        $member = $content['_type'] ?? null;
        $types = $definition['types'] ?? null;

        if (!\is_string($member) || !\is_array($types)) {
            return null;
        }

        $type = $types[$member] ?? null;

        if (!\is_array($type)) {
            return null;
        }

        return $this->convert([...$content, '_component' => $member], $schemas, $type, $stories, $original);
    }

    /**
     * @param array<array-key, mixed>                $content
     * @param array<array-key, mixed>                $schemas
     * @param array<array-key, mixed>                $definition
     * @param array<string, array<array-key, mixed>> $stories
     */
    private function convertReference(
        array $content,
        array $schemas,
        array $definition,
        array $stories,
        mixed $original,
    ): mixed {
        $id = $definition['id'] ?? null;
        $definitions = $schemas['definitions'] ?? null;

        if (!\is_string($id) || !\is_array($definitions)) {
            return null;
        }

        $reference = $definitions[$id] ?? null;

        if (!\is_array($reference)) {
            return null;
        }

        return $this->convert([...$content, '_component' => $id], $schemas, $reference, $stories, $original);
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private static function convertNumber(int|float $content, array $definition): ?string
    {
        return ($definition['type'] ?? null) === 'number' ? (string) $content : null;
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private static function convertBoolean(bool $content, array $definition): ?bool
    {
        return ($definition['type'] ?? null) === 'boolean' ? $content : null;
    }

    /**
     * @param array<array-key, mixed>                $definition
     * @param array<string, array<array-key, mixed>> $stories
     *
     * @return array<array-key, mixed>|string|null
     */
    private function convertString(
        string $content,
        array $definition,
        array $stories,
        mixed $original,
    ): array|string|null {
        $type = $definition['type'] ?? null;

        // Rich text is stored as Markdown in Croct, while Storyblok expects a rich text document.
        if ($type === 'reference' && ($definition['id'] ?? null) === '@croct/richText') {
            try {
                return $this->richTextParser->parse($content);
            } catch (CommonMarkException) {
                return null;
            }
        }

        if ($type === 'reference' && ($definition['id'] ?? null) === '@croct/file') {
            return [
                'id' => null,
                'alt' => null,
                'name' => '',
                'focus' => '',
                'title' => null,
                'filename' => $content,
                'copyright' => null,
                'fieldtype' => 'asset',
                'meta_data' => new \stdClass(),
                'is_external_url' => true,
            ];
        }

        if ($type === 'text') {
            // Link fields may be synced as plain text since Storyblok allows relative URLs,
            // which the URL format does not. The original Storyblok content is the only
            // remaining signal that the field is a link rather than a text.
            if (($definition['format'] ?? null) === 'url' || self::isMultilink($original)) {
                // Links are synced as their cached URL, which for story links is a relative slug.
                // An unchanged value keeps the original link so it still points to the story.
                if (self::isMultilink($original) && ($original['cached_url'] ?? null) === $content) {
                    return $original;
                }

                return [
                    'id' => '',
                    'linktype' => 'url',
                    'fieldtype' => 'multilink',
                    'url' => $content,
                    'cached_url' => $content,
                ];
            }

            // Story relations are synced as UUIDs, which Storyblok resolves into the
            // related stories. A story in the original content indicates a resolved relation.
            if (self::isStory($original)) {
                return $stories[$content] ?? $content;
            }

            return $content;
        }

        return null;
    }

    /**
     * Collects the stories in the content, by UUID.
     *
     * @return array<string, array<array-key, mixed>>
     */
    private static function collectStories(mixed $value): array
    {
        $stories = [];

        self::findStories($value, $stories);

        return $stories;
    }

    /**
     * @param array<string, array<array-key, mixed>> $stories
     */
    private static function findStories(mixed $value, array &$stories): void
    {
        if (!\is_array($value)) {
            return;
        }

        if (self::isStory($value)) {
            $stories[$value['uuid']] = $value;
        }

        foreach ($value as $item) {
            self::findStories($item, $stories);
        }
    }

    /**
     * @phpstan-assert-if-true array{uuid: string, full_slug: string, content: array<array-key, mixed>} $value
     */
    private static function isStory(mixed $value): bool
    {
        return \is_array($value)
            && \is_string($value['uuid'] ?? null)
            && \is_string($value['full_slug'] ?? null)
            && \is_array($value['content'] ?? null)
            && !\array_is_list($value['content']);
    }

    /**
     * @phpstan-assert-if-true array{fieldtype: 'multilink', ...<array-key, mixed>} $value
     */
    private static function isMultilink(mixed $value): bool
    {
        // The fieldtype property is present in every version of the link object,
        // see https://www.storyblok.com/faq/link-object-history
        return \is_array($value) && ($value['fieldtype'] ?? null) === 'multilink';
    }

    private static function getComponentName(string $id): ?string
    {
        $name = \preg_replace('/@.*$/', '', $id);

        if (!\is_string($name) || \trim($name) === '') {
            return null;
        }

        return $name;
    }
}
