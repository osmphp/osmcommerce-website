<?php

declare(strict_types=1);

namespace Osm\Blog\Posts;

use Carbon\Carbon;
use Osm\Data\Markdown\Exceptions\InvalidPath;
use Osm\Core\App;
use Osm\Core\Object_;
use Osm\Framework\Cache\Cache;
use Osm\Framework\Db\Db;
use Osm\Framework\Search\Search;
use function Osm\__;

/**
 * @property ?string $path
 * @property string $root_path
 * @property Db $db
 * @property Search $search
 * @property Cache $cache
 */
class Indexer extends Object_
{
    public function run(bool $rebuild = false): void {
        $this->cache->deleteItem('blog_categories');

        if ($rebuild) {
            $this->clearSearchIndex();
            $this->clearDbIndex();
        }

        $this->indexPath($this->path);
        $this->markDeletedFiles();
    }

    protected function indexPath(?string $path): void {
        $absolutePath = $path
            ? "{$this->root_path}/{$path}"
            : $this->root_path;

        if (is_dir($absolutePath)) {
            foreach (new \DirectoryIterator($absolutePath) as $fileInfo) {
                /* @var \SplFileInfo $fileInfo */
                if ($fileInfo->isDot()) {
                    continue;
                }

                $this->indexPath($path
                    ? "{$path}/{$fileInfo->getFilename()}"
                    : $fileInfo->getFilename());
            }
            return;
        }

        if (is_file($absolutePath)) {
            if (str_ends_with($absolutePath, '.md')) {
                $this->indexFile($path);
            }
            return;
        }

        throw new InvalidPath(__(
            "':path' is and a not valid blog post file path",
            ['path' => $this->path]));
    }

    public function clearDbIndex(): void {
        $this->db->table('posts')->delete();
    }

    public function clearSearchIndex(): void {
        foreach ($this->search->index('posts')->ids() as $id) {
            $this->search->index('posts')->delete($id);
        }
    }

    protected function get_root_path(): string {
        global $osm_app; /* @var App $osm_app */

        return "{$osm_app->paths->data}/posts";
    }

    protected function indexFile(string $path): void {
        $parser = Post::new(['path' => $path]);

        if ($id = $this->db->table('posts')
            ->where('path', $path)
            ->value('id'))
        {
            $this->db->table('posts')
                ->where('id', $id)
                ->update(['deleted_at' => null]);
        }
        else {
            $id = $this->db->table('posts')->insertGetId([
                'path' => $path,
                'modified_at' => $parser->modified_at,
            ]);
        }


        $this->afterSaved($id, $parser);
    }

    protected function get_db(): Db {
        global $osm_app; /* @var App $osm_app */

        return $osm_app->db;
    }

    protected function get_search(): Search {
        global $osm_app; /* @var App $osm_app */

        return $osm_app->search;
    }

    protected function markDeletedFiles() {
        $path = $this->path ?? '';
        if (str_ends_with($path, '.md')) {
            $path = dirname($path);
        }

        $query = $this->db->table('posts');
        if ($path) {
            $query->where('path', 'like', "{$path}/%");
        }

        foreach ($query->get(['id', 'path']) as $item) {
            $absolutePath = "{$this->root_path}/{$item->path}";
            if (!is_file($absolutePath)) {
                $this->db->table('posts')
                    ->where('id', $item->id)
                    ->update(['deleted_at' => Carbon::now()]);
                $this->afterDeleted($item->id);
            }
        }
    }

    protected function afterSaved(int $id, Post $post): void {
        $data = [
            'title' => $post->title,
            'text' => $post->text,
            'year' => $post->created_at->year,
            'month' => $post->created_at->format("Y-m"),
            'created_at' => $post->created_at->format("Y-m-d\TH:i:s"),
            'category' => $post->categories,
        ];

        if ($this->existsInSearch($id)) {
            $this->search->index('posts')
                ->update($id, $data);
        }
        else {
            $this->search->index('posts')
                ->insert(array_merge(['id' => $id], $data));
        }
    }

    protected function afterDeleted(int $id): void {
        if ($this->existsInSearch($id)) {
            $this->search->index('posts')
                ->delete($id);
        }
    }

    protected function existsInSearch(int $id): bool {
        return $this->search->index('posts')
            ->where('id', '=', $id)->id() !== null;
    }

    protected function get_cache(): Cache {
        global $osm_app; /* @var App $osm_app */

        return $osm_app->cache;
    }
}