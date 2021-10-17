<?php

declare(strict_types=1);

namespace Osm\Data\Markdown;

use Carbon\Carbon;
use Michelf\MarkdownExtra;
use Osm\Data\Markdown\Exceptions\InvalidJson;
use Osm\Data\Markdown\Exceptions\TooManyDuplicateHeadings;
use Osm\Core\Exceptions\NotSupported;
use Osm\Core\Object_;
use function Osm\__;
use function Osm\merge;
use Osm\Core\Attributes\Serialized;
use Osm\Framework\Cache\Attributes\Cached;

/**
 * @property string $path #[Serialized]
 *
 * @property string $root_path #[Serialized] Define getter in derived classes
 * @property string $absolute_path #[Serialized]
 * @property bool $exists #[Serialized]
 * @property Carbon $modified_at #[Serialized]
 * @property string $original_text #[Serialized]
 * @property string $original_html #[Serialized]
 * @property ?string $title #[Serialized]
 * @property ?string $title_html #[Serialized]
 * @property \stdClass $toc #[Serialized]
 * @property \stdClass $meta #[Serialized]
 * @property string $text #[Serialized]
 * @property string $html #[Serialized]
 * @property string $reading_time #[Serialized]
 * @property ?string $abstract #[Serialized]
 * @property ?string $abstract_html #[Serialized]
 * @property ?string $meta_description #[Serialized]
 * @property ?string $canonical_url #[Serialized]
 *
 * @property PlaceholderRenderer $placeholder_renderer
 *      #[Cached('{placeholder_renderer_cache_key}')]
 * @property string $placeholder_renderer_cache_key
 */
class File extends Object_
{
    const MAX_DUPLICATE_HEADINGS = 100;

    // Regex patterns
    const H1_PATTERN = '/^#\s*(?<title>[^#{\r\n]+)[\r\n]*/mu';
    const SECTION_PATTERN = '/^(?<depth>#+)\s*(?<title>[^#{\r\n]+)#*[ \t]*(?:{(?<attributes>[^}\r\n]*)})?\r?$[\r\n]*(?<text>[\s\S]*?)(?=^#|\Z)/mu';

    // obsolete patterns
    const HEADER_PATTERN = '/^(?<depth>#+)\s*(?<title>[^#{\r\n]+)#*[ \t]*(?:{(?<attributes>[^}\r\n]*)})?\r?$/mu';
    const IMAGE_LINK_PATTERN = "/!\\[(?<description>[^\\]]*)\\]\\((?<url>[^\\)]+)\\)/u";

    // [title](url), but not ![title](url)
    const LINK_PATTERN_1 = '/(?<!\!)\[(?<title>[^]]*)\]\((?<url>[^)]*)\)/u';

    // <url>
    const LINK_PATTERN_2 = '/\<(?<url>[^>]*)\>/u';


    protected function get_absolute_path(): string {
        return "{$this->root_path}/{$this->path}";
    }

    protected function get_exists(): bool {
        return file_exists($this->absolute_path);
    }

    protected function get_original_text(): string {
        $this->assumeExists();
        return file_get_contents($this->absolute_path);
    }

    protected function assumeExists() {
        if (!$this->exists) {
            throw new NotSupported(__(
                "Before processing ':file', check that it exists using the 'exists' property",
                ['file' => $this->absolute_path],
            ));
        }
    }

    protected function get_title(): string {
        $this->parseText();
        return $this->title;
    }

    protected function get_text(): string {
        $this->parseText();
        return $this->text;
    }

    protected function get_meta(): \stdClass {
        $this->parseText();
        return $this->meta;
    }

    protected function get_toc(): \stdClass {
        $this->parseText();
        return $this->toc;
    }

    protected function parseText(): void {
        $text = $this->original_text;

        $text = $this->parseTitle($text);
        $text = $this->parseSections($text);

        $this->text = $text;
    }

    protected function parseTitle(string $text): string {
        $this->title = '';

        return preg_replace_callback(static::H1_PATTERN, function($match) {
            $this->title = $match['title'];

            return '';
        }, $text);
    }

    protected function parseSections(string $text): string {
        $this->meta = new \stdClass();
        $this->toc = new \stdClass();

        return preg_replace_callback(static::SECTION_PATTERN, function($match) {
            if ($match['title'] === 'meta') {
                if (($json = json_decode($match['text'])) === null) {
                    throw new InvalidJson(__(
                        "Invalid JSON in 'meta' section of ':file' file",
                        ['file' => $this->path]));
                }
                $this->meta = merge($this->meta, $json);

                return '';
            }

            if (str_starts_with($match['title'], 'meta.')) {
                $property = substr($match['title'], strlen('meta.'));
                $this->meta->$property = $match['text'];

                return '';
            }

            $id = $this->generateUniqueId($match['title']);

            $this->toc->$id = (object)[
                'depth' => mb_strlen($match['depth']),
                'title' => $match['title'],
            ];

            // by default, keep the section in the text
            return "{$match['depth']} {$match['title']} {$match['depth']} " .
                "{#{$id}} \n\n{$match['text']}";
        }, $text);
    }

    protected function generateUniqueId(string $heading): string {
        $id = $this->generateId($heading);

        for ($i = 0; $i < static::MAX_DUPLICATE_HEADINGS; $i++) {
            $suffixedId = $i === 0
                ? $id
                : "{$id}-$i";

            if (!isset($this->toc->$suffixedId)) {
                return $suffixedId;
            }
        }

        throw new TooManyDuplicateHeadings(__("Too many ':heading' headings",
            ['heading' => $heading]));
    }

    protected function generateId(string $heading): string {
        $id = mb_strtolower($heading);

        $id = preg_replace('/[^\w\d\- ]+/u', ' ', $id);
        $id = preg_replace('/\s+/u', '-', $id);
        $id = preg_replace('/^\-+/u', '', $id);
        $id = preg_replace('/\-+$/u', '', $id);

        return $id;
    }

    protected function get_modified_at(): Carbon {
        $this->assumeExists();
        return Carbon::createFromTimestamp(filemtime($this->absolute_path));
    }

    protected function get_html(): ?string {
        return $this->html($this->text);
    }

    protected function get_original_html(): ?string {
        return MarkdownExtra::defaultTransform($this->original_text);
    }

    protected function get_reading_time(): string {
        $minutes = round(str_word_count($this->text) / 300.0);

        return $minutes > 1
            ? __(":minutes minutes read", ['minutes' => $minutes])
            : __("1 minute read");
    }

    protected function get_title_html(): string {
        return $this->inlineHtml($this->title);
    }

    protected function html(?string $markdown): ?string {
        if (!$markdown) {
            return null;
        }

        $markdown = $this->transformRelativeLinks($markdown);

        $markdown = $this->placeholder_renderer->render($this, $markdown);

        $html = MarkdownExtra::defaultTransform($markdown);

        // fix code blocks
        return str_replace("\n</code>", '</code>', $html);
    }

    protected function transformRelativeLinks(string $markdown): string {
        $markdown = preg_replace_callback(static::LINK_PATTERN_1, function($match) {
            return ($url = $this->resolveRelativeUrl($match['url']))
                ? "[{$match['title']}]({$url})"
                : $match[0];
        }, $markdown);

        return preg_replace_callback(static::LINK_PATTERN_2, function($match) {
            return ($url = $this->resolveRelativeUrl($match['url']))
                ? "<{$url}>"
                : $match[0];
        }, $markdown);
    }

    protected function resolveRelativeUrl(string $path): ?string {
        $path = $this->removeHashTag($path, $hashTag);

        $absolutePath = realpath(dirname($this->absolute_path) . '/' . $path);

        return $path && $absolutePath &&
            ($url = $this->generateRelativeUrl($absolutePath))
                ? $url . $hashTag
                : null;
    }

    protected function generateRelativeUrl(string $absolutePath): ?string {
        return null;
    }

    protected function inlineHtml(?string $markdown): ?string {
        if (!$markdown) {
            return null;
        }

        $html = $this->html($markdown);

        return trim(str_replace(['<p>', '</p>'], '', $html));
    }

    protected function get_placeholder_renderer_cache_key(): string {
        return "placeholder_renderer__{$this->__class->name}";
    }

    protected function get_placeholder_renderer(): PlaceholderRenderer {
        return PlaceholderRenderer::new(['class_name' => $this->__class->name]);
    }

    protected function get_abstract(): ?string {
        return $this->meta->abstract ?? null;
    }

    protected function get_abstract_html(): ?string {
        return $this->html($this->abstract);
    }

    protected function get_meta_description(): ?string {
        return $this->meta?->description ?? $this->abstract;
    }

    protected function get_canonical_url(): ?string {
        return $this->meta->canonical_url ?? null;
    }

    protected function removeHashTag(?string $url, ?string &$hashTag = null)
        : ?string
    {
        if (!$url) {
            return $url;
        }

        if (($pos = mb_strpos($url, '#')) !== false) {
            $hashTag = mb_substr($url, $pos);
            $url = mb_substr($url, 0, $pos);
        }

        return $url;
    }
}