<?php

namespace UrlShortener\Test\Unit;

use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use UrlShortener\UrlShortener;
use PHPUnit\Framework\TestCase;
use UrlShortener\DataProviderInterface;
use UrlShortener\Exception\DuplicateKeyException;
use UrlShortener\Exception\InvalidConfigException;

class FakeDataProvider implements DataProviderInterface
{
    public function put(string $key, string $value): void
    {
    }

    public function get(string $key): ?string
    {
        return '';
    }
}

class UrlShortenerTest extends TestCase
{
    private $baseUrl = 'https://url-shortener.test';

    public function setUp()
    {
        Configure::write('UrlShortener', [
            UrlShortener::CONFIG_DATA_PROVIDER_KEY => FakeDataProvider::class,
            UrlShortener::CONFIG_BASE_URL_KEY => $this->baseUrl,
        ]);
    }

    public function testConstructorShouldThrowExceptionIfDataProviderNotDefined()
    {
        Configure::write('UrlShortener.dataProvider', '');
        $this->expectException(InvalidConfigException::class);
        new UrlShortener();
    }

    public function testConstructorShouldThrowExceptionIfDataProviderNotExist()
    {
        Configure::write('UrlShortener.dataProvider', 'SomeFakeClass');
        $this->expectException(InvalidConfigException::class);
        new UrlShortener();
    }

    public function testConfigurable()
    {
        $defaultShortener = new UrlShortener();
        self::assertEquals(6, $defaultShortener->getConfig(UrlShortener::CONFIG_SHORT_ULR_LENGTH_KEY));
        self::assertFalse($defaultShortener->getConfig(UrlShortener::CONFIG_RETRY_ON_DUPLICATE_KEY));

        Configure::write('UrlShortener', [
            UrlShortener::CONFIG_DATA_PROVIDER_KEY => FakeDataProvider::class,
            UrlShortener::CONFIG_SHORT_ULR_LENGTH_KEY => 10,
            UrlShortener::CONFIG_RETRY_ON_DUPLICATE_KEY => true,
        ]);
        $customShortener = new UrlShortener();
        self::assertEquals(10, $customShortener->getConfig(UrlShortener::CONFIG_SHORT_ULR_LENGTH_KEY));
        self::assertTrue($customShortener->getConfig(UrlShortener::CONFIG_RETRY_ON_DUPLICATE_KEY));
    }

    public function testCreateMethodShouldTriggerTwoEvents()
    {
        // Mock event listener
        $mock = $this->getMockBuilder('stdClass')
            ->setMethods(['eventListener'])
            ->getMock();

        $mock->expects($this->exactly(2))
            ->method('eventListener')
            ->with(
                $this->isInstanceOf(Event::class)
            );

        EventManager::instance()->on(UrlShortener::EVENT_BEFORE_SHORTEN, [$mock, 'eventListener']);
        EventManager::instance()->on(UrlShortener::EVENT_AFTER_SHORTEN, [$mock, 'eventListener']);

        $urlShortener = new UrlShortener();
        $urlShortener->shorten('someUrl');
    }

    public function testCreateMethodShouldFail()
    {
        /** @var DataProviderInterface|\PHPUnit_Framework_MockObject_MockObject $dataProvider */
        $dataProvider = $this->getDataProviderMock();
        $dataProvider->method('put')
            ->will($this->throwException(new DuplicateKeyException('fake_key')));

        $eventListener = $this->getEventListenerMock();
        // Ensure that EVENT_CREATE_FAIL has been triggered
        $eventListener->expects($this->once())
            ->method('eventListener')
            ->with(
                $this->isInstanceOf(Event::class)
            );

        // Register event listener.
        EventManager::instance()->on(UrlShortener::EVENT_SHORTEN_FAIL, [$eventListener, 'eventListener']);
        $urlShortener = new UrlShortener();
        $urlShortener->setDataProvider($dataProvider);
        $this->expectException(DuplicateKeyException::class);
        $urlShortener->shorten('some_url');
    }

    /**
     * Test that create method will not fail and retry create new key if `retryOnDuplicate` setting set to true.
     */
    public function testCreateMethodShouldNotFail()
    {
        /** @var DataProviderInterface|\PHPUnit_Framework_MockObject_MockObject $dataProvider */
        $dataProvider = $this->getDataProviderMock();
        $dataProvider->method('put')
            // On first call throw Duplicate exception.
            // On second call do nothing - everything is ok.
            ->will($this->onConsecutiveCalls(
                $this->throwException(new DuplicateKeyException('fake_key')),
                $this->returnValue(null)
            ));

        // Mock event listener
        $eventListener = $this->getEventListenerMock();
        // Ensure that EVENT_CREATE_FAIL has no been triggered
        $eventListener->expects($this->exactly(0))
            ->method('eventListener');

        $urlLength = 6;
        Configure::write('UrlShortener', [
            UrlShortener::CONFIG_DATA_PROVIDER_KEY => FakeDataProvider::class,
            UrlShortener::CONFIG_SHORT_ULR_LENGTH_KEY => $urlLength,
            UrlShortener::CONFIG_RETRY_ON_DUPLICATE_KEY => true,
            UrlShortener::CONFIG_BASE_URL_KEY => $this->baseUrl,
        ]);

        // Register event listener.
        EventManager::instance()->on(UrlShortener::EVENT_SHORTEN_FAIL, [$eventListener, 'eventListener']);
        EventManager::instance()->on(UrlShortener::EVENT_SHORTEN_FAIL, function(Event $event) {
            self::assertArrayHasKey('shortUrl', $event->data);
        });
        $urlShortener = new UrlShortener();
        $urlShortener->setDataProvider($dataProvider);
        $shortUrl = $urlShortener->shorten('some_url');
        self::assertNotNull($shortUrl);
        preg_match('/(?:[a-z](?!\/))+$/i', $shortUrl, $shortUrlHash);
        self::assertEquals($urlLength, strlen($shortUrlHash[0]));
    }

    public function testExpandMethodTriggerEventsOnSuccess()
    {
        $fullUrl = 'success_decoded_url';
        $dataProvider = $this->getDataProviderMock();
        $dataProvider->method('get')
            ->will($this->returnValue($fullUrl));

        $eventListener = $this->getEventListenerMock();
        $eventListener->expects($this->exactly(2))
            ->method('eventListener');

        /** @var \PHPUnit_Framework_MockObject_MockObject|ServerRequest $request */
        $request = $this->getMockBuilder(ServerRequest::class)
            ->getMock();
        $request->url = 'http://some.url.com/l/UddskE';

        // Register event listeners.
        EventManager::instance()->on(UrlShortener::EVENT_BEFORE_EXPAND, [$eventListener, 'eventListener']);
        EventManager::instance()->on(UrlShortener::EVENT_BEFORE_EXPAND, function(Event $event) {
            self::assertArrayHasKey('request', $event->data);
        });
        EventManager::instance()->on(UrlShortener::EVENT_AFTER_EXPAND, [$eventListener, 'eventListener']);
        EventManager::instance()->on(UrlShortener::EVENT_AFTER_EXPAND, function(Event $event) {
            self::assertArrayHasKey('shortUrl', $event->data);
            self::assertArrayHasKey('fullUrl', $event->data);
        });
        $urlShorter = new UrlShortener();
        $urlShorter->setDataProvider($dataProvider);
        $result = $urlShorter->expandByRequest($request);
        self::assertEquals($fullUrl, $result);
    }

    public function testExpandMethodShouldTriggerFailEvent()
    {
        $dataProvider = $this->getDataProviderMock();
        $dataProvider->method('get')
            ->will($this->returnValue(null));

        $eventListener = $this->getEventListenerMock();
        $eventListener->expects($this->exactly(1))
            ->method('eventListener');

        /** @var \PHPUnit_Framework_MockObject_MockObject|ServerRequest $request */
        $request = $this->getMockBuilder(ServerRequest::class)
            ->getMock();
        $request->url = 'http://some.url.com/l/UddskE';

        // Register event listener.
        EventManager::instance()->on(UrlShortener::EVENT_EXPAND_FAIL, [$eventListener, 'eventListener']);
        EventManager::instance()->on(UrlShortener::EVENT_EXPAND_FAIL, function(Event $event) {
            self::assertArrayHasKey('shortUrl', $event->data);
        });

        $urlShorter = new UrlShortener();
        $urlShorter->setDataProvider($dataProvider);
        $result = $urlShorter->expandByRequest($request);
        self::assertNull($result);
    }

    public function testManuallyCreatingShortUrl()
    {
        /** @var DataProviderInterface|\PHPUnit_Framework_MockObject_MockObject $dataProvider */
        $dataProvider = $this->getDataProviderMock();
        $dataProvider->method('put')
            // On first call throw Duplicate exception.
            // On second call do nothing - everything is ok.
            ->will($this->onConsecutiveCalls(
                $this->returnValue(null)
            ));

        // Mock event listener
        $eventListener = $this->getEventListenerMock();
        // Ensure that EVENT_CREATE_FAIL has no been triggered
        $eventListener->expects($this->exactly(0))
            ->method('eventListener');

        $urlLength = 6;
        Configure::write('UrlShortener', [
            UrlShortener::CONFIG_DATA_PROVIDER_KEY    => FakeDataProvider::class,
            UrlShortener::CONFIG_SHORT_ULR_LENGTH_KEY => $urlLength,
            UrlShortener::CONFIG_BASE_URL_KEY         => $this->baseUrl,
        ]);

        // Register event listener.
        EventManager::instance()->on(UrlShortener::EVENT_SHORTEN_FAIL, [$eventListener, 'eventListener']);
        $urlShortener = new UrlShortener();
        $urlShortener->setDataProvider($dataProvider);
        $shortUrl = $urlShortener->shorten('some_url', 'AWESOME_URL');
        self::assertNotNull($shortUrl);
        preg_match('/(?:\w(?!\/))+$/i', $shortUrl, $shortUrlHash);
        self::assertEquals('AWESOME_URL', $shortUrlHash[0]);
    }


    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getEventListenerMock(): \PHPUnit_Framework_MockObject_MockObject
    {
        return $this->getMockBuilder('stdClass')
            ->setMethods(['eventListener'])
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|DataProviderInterface
     */
    private function getDataProviderMock(): \PHPUnit_Framework_MockObject_MockObject
    {
        return $this->getMockBuilder(FakeDataProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
