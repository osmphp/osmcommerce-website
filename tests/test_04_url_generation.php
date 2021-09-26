<?php

declare(strict_types=1);

namespace My\Tests;

use My\Posts\PageType;
use My\Posts\Posts;
use Osm\Framework\TestCase;

class test_04_url_generation extends TestCase
{
    public string $app_class_name = \My\Samples\App::class;

    public function test_applying_category_filter_on_home_page() {
        // GIVEN the home page
        $posts = Posts::new([
            'page_type' => PageType\Home::new(),
            'http_query' => [],
            'base_url' => null,
        ]);

        /* @var \Osm\Blog\Categories\Module $categoryModule */
        $categoryModule = $this->app->modules[\Osm\Blog\Categories\Module::class];
        $category = $categoryModule->categories['osmsoftware'];

        // WHEN you apply category filter
        // THEN category page is rendered
        $this->assertEquals('/blog/osmsoftware/',
            (string)$posts->url()->addCategoryFilter($category));
    }

    public function test_applying_two_category_filters_on_home_page() {
        // GIVEN the home page
        $posts = Posts::new([
            'page_type' => PageType\Home::new(),
            'http_query' => [],
            'base_url' => null,
        ]);

        /* @var \Osm\Blog\Categories\Module $categoryModule */
        $categoryModule = $this->app->modules[\Osm\Blog\Categories\Module::class];
        $category1 = $categoryModule->categories['osmsoftware'];
        $category2 = $categoryModule->categories['framework'];

        // WHEN you apply category filter
        // THEN category page is rendered
        $this->assertEquals('/blog/?category=framework+osmsoftware',
            (string)$posts->url()
                ->addCategoryFilter($category1)
                ->addCategoryFilter($category2));
    }

    public function test_applying_category_filter_on_category_page() {
        // GIVEN the home page
        $posts = Posts::new([
            'page_type' => PageType\Category::new([
                'category_url_key' => 'osmsoftware',
            ]),
            'http_query' => [],
            'base_url' => null,
        ]);

        /* @var \Osm\Blog\Categories\Module $categoryModule */
        $categoryModule = $this->app->modules[\Osm\Blog\Categories\Module::class];
        $category = $categoryModule->categories['framework'];

        // WHEN you apply category filter
        // THEN category page is rendered
        $this->assertEquals('/blog/?category=framework+osmsoftware',
            (string)$posts->url()->addCategoryFilter($category));
    }

}