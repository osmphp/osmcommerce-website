<?php

declare(strict_types=1);

namespace Osm\Blog\Posts\AppliedFilter;

use Osm\Blog\Posts\AppliedFilter;
use Osm\Blog\Posts\Url;
use function Osm\__;

/**
 * @property string $phrase
 */
class Search extends AppliedFilter
{
    protected function get_title(): string {
        return __("Search");
    }

    protected function get_value(): string {
        return $this->phrase;
    }

    protected function get_clear_url(): string|Url {
        return $this->collection->url()->removeSearch();
    }
}