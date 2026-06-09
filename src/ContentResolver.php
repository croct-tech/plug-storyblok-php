<?php

declare(strict_types=1);

namespace Croct\Plug\Storyblok;

use Croct\Plug\Exception\CroctException;
use Croct\Plug\FetchResponse;
use Croct\Plug\Uuid;

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

    /**
     * @param (callable(): string)|null $uidGenerator Generates the `_uid` of each converted structure.
     */
    public function __construct(?callable $uidGenerator = null)
    {
        $this->uidGenerator = $uidGenerator ?? static fn (): string => Uuid::random()->toString();
    }

    /**
     * Walks the content tree, replacing every `croct` marker with the fetched, converted content.
     *
     * @param callable(string): ?FetchResponse $fetcher Fetches the content of a slot by ID.
     */
    public function resolve(mixed $content, callable $fetcher): mixed
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

            return $this->create($response->getContent(), $response->getMetadata()?->getSchema()) ?? $rest;
        }

        $result = [];

        foreach ($content as $key => $value) {
            $result[$key] = $this->resolve($value, $fetcher);
        }

        return $result;
    }

    /**
     * Converts Croct content to the Storyblok shape using the content schema.
     *
     * @param array<array-key, mixed>|null $schema
     *
     * @return array<array-key, mixed>|null The Storyblok content, or null when it cannot be converted.
     */
    public function create(mixed $content, ?array $schema): ?array
    {
        if ($schema === null) {
            return null;
        }

        $root = $schema['root'] ?? null;

        if (!\is_array($root)) {
            return null;
        }

        $result = $this->convert($content, $schema, $root);

        return \is_array($result) ? $result : null;
    }

    /**
     * @param array<array-key, mixed> $schemas
     * @param array<array-key, mixed> $definition
     */
    private function convert(mixed $content, array $schemas, array $definition): mixed
    {
        if (\is_int($content) || \is_float($content)) {
            return self::convertNumber($content, $definition);
        }

        if (\is_bool($content)) {
            return self::convertBoolean($content, $definition);
        }

        if (\is_string($content)) {
            return self::convertString($content, $definition);
        }

        if (\is_array($content)) {
            return \array_is_list($content)
                ? $this->convertArray($content, $schemas, $definition)
                : $this->convertObject($content, $schemas, $definition);
        }

        return null;
    }

    /**
     * @param list<mixed>             $content
     * @param array<array-key, mixed> $schemas
     * @param array<array-key, mixed> $definition
     *
     * @return list<mixed>|null
     */
    private function convertArray(array $content, array $schemas, array $definition): ?array
    {
        if (($definition['type'] ?? null) !== 'list') {
            return null;
        }

        $items = $definition['items'] ?? null;

        if (!\is_array($items)) {
            return null;
        }

        $elements = [];

        foreach ($content as $item) {
            $element = $this->convert($item, $schemas, $items);

            if ($element === null) {
                return null;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @param array<array-key, mixed> $content
     * @param array<array-key, mixed> $schemas
     * @param array<array-key, mixed> $definition
     */
    private function convertObject(array $content, array $schemas, array $definition): mixed
    {
        return match ($definition['type'] ?? null) {
            'structure' => $this->convertStructure($content, $schemas, $definition),
            'union' => $this->convertUnion($content, $schemas, $definition),
            'reference' => $this->convertReference($content, $schemas, $definition),
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed> $content
     * @param array<array-key, mixed> $schemas
     * @param array<array-key, mixed> $definition
     *
     * @return array<array-key, mixed>|null
     */
    private function convertStructure(array $content, array $schemas, array $definition): ?array
    {
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

            $element = $this->convert($value, $schemas, $type);

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
     * @param array<array-key, mixed> $content
     * @param array<array-key, mixed> $schemas
     * @param array<array-key, mixed> $definition
     */
    private function convertUnion(array $content, array $schemas, array $definition): mixed
    {
        $member = $content['_type'] ?? null;
        $types = $definition['types'] ?? null;

        if (!\is_string($member) || !\is_array($types)) {
            return null;
        }

        $type = $types[$member] ?? null;

        if (!\is_array($type)) {
            return null;
        }

        return $this->convert([...$content, '_component' => $member], $schemas, $type);
    }

    /**
     * @param array<array-key, mixed> $content
     * @param array<array-key, mixed> $schemas
     * @param array<array-key, mixed> $definition
     */
    private function convertReference(array $content, array $schemas, array $definition): mixed
    {
        $id = $definition['id'] ?? null;
        $definitions = $schemas['definitions'] ?? null;

        if (!\is_string($id) || !\is_array($definitions)) {
            return null;
        }

        $reference = $definitions[$id] ?? null;

        if (!\is_array($reference)) {
            return null;
        }

        return $this->convert([...$content, '_component' => $id], $schemas, $reference);
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
     * @param array<array-key, mixed> $definition
     *
     * @return array<array-key, mixed>|string|null
     */
    private static function convertString(string $content, array $definition): array|string|null
    {
        $type = $definition['type'] ?? null;

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
            if (($definition['format'] ?? null) === 'url') {
                return [
                    'id' => '',
                    'linktype' => 'url',
                    'fieldtype' => 'multilink',
                    'url' => $content,
                    'cached_url' => $content,
                ];
            }

            return $content;
        }

        return null;
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
