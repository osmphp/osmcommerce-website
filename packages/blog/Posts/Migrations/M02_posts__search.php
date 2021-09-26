<?php

declare(strict_types=1);

namespace Osm\Blog\Posts\Migrations;

use Osm\Core\App;
use Osm\Framework\Migrations\Migration;
use Osm\Framework\Search\Blueprint;
use Osm\Framework\Search\Search;

/**
 * @property Search $search
 */
class M02_posts__search extends Migration
{
    protected function get_search(): Search {
        global $osm_app; /* @var App $osm_app */

        return $osm_app->search;
    }

    public function create(): void {
        if ($this->search->exists('posts')) {
            $this->search->drop('posts');
        }

        $this->search->create('posts', function (Blueprint $index) {
            $index->string('title')
                ->searchable();
            $index->string('text')
                ->searchable();
            $index->string('tags')
                ->array()
                ->searchable()
                ->filterable();
            $index->string('series')
                ->searchable()
                ->filterable();
            $index->string('created_at')
                ->sortable();
            $index->int('year')
                ->filterable()
                ->faceted();
            $index->string('month')
                ->filterable()
                ->faceted();
            $index->string('category')
                ->array()
                ->filterable()
                ->faceted();
        });
    }

    public function drop(): void {
        $this->search->drop('posts');
    }
}