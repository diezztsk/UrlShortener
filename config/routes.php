<?php

use Cake\Routing\Router;
use Cake\Routing\RouteBuilder;
use UrlShortener\UrlShortener;

$shortUrlPath = \Cake\Core\Configure::read('UrlShortener.' . UrlShortener::CONFIG_SHORT_URL_PATH_KEY);
if (null !== $shortUrlPath) {
    Router::plugin(
        'UrlShortener',
        ['path' => '/' . $shortUrlPath],
        function(RouteBuilder $routes) {
            $routes->connect('/:hash',
                ['controller' => 'ExpandShortUrl', 'action' => 'index'],
                ['hash' => '[a-zA-Z]+']
            );
        }
    );
}
