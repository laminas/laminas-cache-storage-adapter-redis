<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Redis;

class RedisFromExtensionAsset extends Redis
{
    /**
     * It seems that the extension creates and sets the `socket` property to `true` once the object is initialized.
     * {@see \Laminas\Cache\Storage\Adapter\RedisResourceManager::setResource()}
     * Since PHP 8.2 deprecates dynamic property creation, we are using this asset until the redis extension
     * will provide this property.
     */
    public bool $socket = false;
}
