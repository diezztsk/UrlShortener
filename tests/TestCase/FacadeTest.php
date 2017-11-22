<?php

namespace UrlShortener\Test\Unit;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use UrlShortener\DataProviderInterface;
use UrlShortener\UrlShortenerFacade;
use UrlShortener\UrlShortener;
use PHPUnit\Framework\TestCase;

class FacadeTestFakeDataProvider implements DataProviderInterface
{
    private $storage = [];

    public function put(string $key, string $value): void
    {
        $this->storage[$key] = $value;
    }

    public function get(string $key): ?string
    {
        if (!array_key_exists($key, $this->storage)) {
            return null;
        }

        return $this->storage[$key];
    }
}

class FacadeTest extends TestCase
{
    private $baseUrl = 'https://url-shortener.test';

    public function setUp()
    {
        Configure::write('UrlShortener', [
            UrlShortener::CONFIG_DATA_PROVIDER_KEY => FacadeTestFakeDataProvider::class,
            UrlShortener::CONFIG_BASE_URL_KEY => $this->baseUrl,
        ]);
    }

    public function testFacadeMethods()
    {
        $shortUrl = UrlShortenerFacade::shorten('test');
        $fullUrl = UrlShortenerFacade::expandByRequest($this->getMockRequest($shortUrl));
        self::assertEquals('test', $fullUrl);
    }

    private function getMockRequest(string $value)
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|ServerRequest $request */
        $request = $this->getMockBuilder(ServerRequest::class)
            ->getMock();
        $request->url = $value;

        return $request;
    }
}