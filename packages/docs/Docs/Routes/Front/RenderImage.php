<?php

namespace Osm\Docs\Docs\Routes\Front;

use Osm\Core\Exceptions\NotImplemented;
use Symfony\Component\HttpFoundation\Response;

/**
 * @property string $path
 */
class RenderImage extends VersionRoute
{
    public function run(): Response {
        throw new NotImplemented($this);
    }
}