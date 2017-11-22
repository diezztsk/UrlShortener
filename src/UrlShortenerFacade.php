<?php

namespace UrlShortener;

use Cake\Http\ServerRequest;

/**
 * Class Facade
 *
 * @method static null|string  expandByRequest(ServerRequest $request)
 * @method static null|string  expandByHash(string $shortUrl)
 * @method static string  shorten(string $fullUrl, string $hash = null)
 *
 * @package UrlShortener
 */
class UrlShortenerFacade
{
    /**
     * @var UrlShortener
     */
    private static $instance;

    /**
     * Proxy static calls to facade instance.
     *
     * @param $name
     * @param $arguments
     *
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $instance = static::getFacadeInstance();
        if (!method_exists($instance, $name)) {
            throw new \InvalidArgumentException('Method ' . $name . 'does\'t exist');
        }

        return call_user_func_array([$instance, $name], $arguments);
    }

    /**
     * Return UrlShortener instance.
     *
     * @throws Exception\InvalidConfigException if dataProvider not defined in the App config.
     * @throws \Cake\Core\Exception\Exception When trying to set a key that is invalid.
     * @return mixed
     */
    protected static function getFacadeInstance()
    {
        if (null === static::$instance) {
            static::$instance = new UrlShortener();
        }

        return static::$instance;
    }
}
