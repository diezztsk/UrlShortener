<?php

namespace UrlShortener\Controller;

use Cake\Http\Response;
use UrlShortener\UrlShortenerFacade as UrlShortener;
use Cake\Network\Exception\NotFoundException;
use App\Controller\AppController as BaseController;

/**
 * Class ResolveShortUrlController
 *
 * @package UrlShortener\Controller
 */
class ExpandShortUrlController extends BaseController
{
    /**
     * Resolve short url and redirect to full url.
     * If short url is't exist 404 error will be returned.
     *
     * @throws NotFoundException
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $fullUrl = UrlShortener::expandByRequest($this->request);
        if (null === $fullUrl) {
            throw new NotFoundException();
        }

        return $this->redirect($fullUrl);
    }
}
