<?php
global $osm_app; /* @var \Osm\Core\App $osm_app */
/* @var \Osm\Blog\Posts\Posts $posts */
?>
<x-std-pages::layout title='Blog | Osm Software' description="The latest posts about
    Osm Framework and its ecosystem.">

    <div class="container mx-auto px-4 grid grid-cols-12 gap-4">
        <section class="col-start-1 col-span-12 md:col-start-5 md:col-span-8 lg:col-start-4 md:col-span-9">
            <h1 class="text-2xl sm:text-4xl font-bold my-8">
                {{ \Osm\__("Latest Posts") }}
            </h1>

            @forelse($posts->items as $post)
                <x-posts::list-item :post="$post" />
            @empty
                <p class="my-4">
                    {{ \Osm\__("No posts here.") }}
                </p>
            @endforelse
        </section>
        <aside class="left-drawer left-drawer--closed">
            <x-posts::applied_filters :posts="$posts"/>

            @foreach ($posts->filters as $filter)
                @if ($filter->visible)
                    <x-dynamic-component :component="$filter->component"
                        :filter="$filter" />
                @endif
            @endforeach
        </aside>
    </div>
</x-std-pages::layout>