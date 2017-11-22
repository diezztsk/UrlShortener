<?php

namespace UrlShortener;

use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\EventDispatcherTrait;
use League\Uri\Schemes\Http as HttpUri;
use UrlShortener\Exception\DuplicateKeyException;
use UrlShortener\Exception\InvalidConfigException;

/**
 * Class UrlShortener
 *
 * @package UrlShortener
 */
class UrlShortener
{
    use EventDispatcherTrait, InstanceConfigTrait;

    /**
     * Supported events name.
     */
    const EVENT_BEFORE_EXPAND  = 'url.shortener.event.before.expand';
    const EVENT_AFTER_EXPAND   = 'url.shortener.event.after.expand';
    const EVENT_EXPAND_FAIL    = 'url.shortener.event.expand.fail';
    const EVENT_BEFORE_SHORTEN = 'url.shortener.event.before.shorten';
    const EVENT_AFTER_SHORTEN  = 'url.shortener.event.after.shorten';
    const EVENT_SHORTEN_FAIL   = 'url.shortener.event.shorten.fail';

    /**
     * Config keys constants.
     */
    const CONFIG_DATA_PROVIDER_KEY      = 'dataProvider';
    const CONFIG_SHORT_ULR_LENGTH_KEY   = 'urlLength';
    const CONFIG_RETRY_ON_DUPLICATE_KEY = 'retryOnDuplicate';
    const CONFIG_BASE_URL_KEY           = 'baseUrl';
    const CONFIG_SHORT_URL_PATH_KEY     = 'shortUrlPath';
    const CONFIG_HASH_GENERATOR         = 'hashGenerator';

    /**
     * @var DataProviderInterface
     */
    protected $dataProvider;
    /**
     * User defined callback that generate short url hash.
     *
     * @var callable
     */
    protected $hashGenerator;

    /**
     * Default plugin config.
     *
     * @var array
     */
    protected $_defaultConfig = [
        self::CONFIG_SHORT_ULR_LENGTH_KEY   => 6,
        self::CONFIG_RETRY_ON_DUPLICATE_KEY => false,
        self::CONFIG_SHORT_URL_PATH_KEY     => null,
        self::CONFIG_HASH_GENERATOR         => null,
    ];

    /**
     * UrlShortener constructor.
     *
     * @throws InvalidConfigException if dataProvider not defined in the App config.
     * @throws \Cake\Core\Exception\Exception When trying to set a key that is invalid.
     */
    public function __construct()
    {
        $this->setConfig(Configure::read('UrlShortener'));
        $dataProviderClass = $this->getConfig(self::CONFIG_DATA_PROVIDER_KEY);
        if (!$dataProviderClass || !class_exists($dataProviderClass)) {
            throw new InvalidConfigException('You must define \'UrlShortener.dataProvider\' in the App config');
        }
        $this->setDataProvider(new $dataProviderClass);
        $hashGenerator = $this->getConfig(self::CONFIG_HASH_GENERATOR);
        if (null !== $hashGenerator) {
            $this->setHashGenerator($hashGenerator);
        }
    }

    /**
     * Set data provider implementation.
     *
     * @param DataProviderInterface $dataProvider
     *
     * @return $this
     */
    public function setDataProvider(DataProviderInterface $dataProvider)
    {
        $this->dataProvider = $dataProvider;
        return $this;
    }

    /**
     * Set user defined short url generator.
     *
     * @param callable $hashGenerator
     *
     * @return $this
     */
    public function setHashGenerator(callable $hashGenerator)
    {
        $this->hashGenerator = $hashGenerator;
        return $this;
    }

    /**
     * Fetch full url server request.
     *
     * @param ServerRequest $request
     *
     * @return null|string
     */
    public function expandByRequest(ServerRequest $request):?string
    {
        $this->triggerEvent(self::EVENT_BEFORE_EXPAND, compact('request'));
        preg_match('/(?:\w(?!\/))+$/i', $request->url, $match);
        if (empty($match)) {
            $this->triggerEvent(self::EVENT_EXPAND_FAIL, compact('request'));
            return null;
        }
        $shortUrl = $match[0];
        return $this->expand($shortUrl);
    }

    /**
     * Fetch full url by short url hash.
     *
     * @param string $shortUrl
     *
     * @return null|string
     */
    public function expandByHash(string $shortUrl):?string
    {
        $this->triggerEvent(self::EVENT_BEFORE_EXPAND, compact('shortUrl'));
        return $this->expand($shortUrl);
    }

    /**
     * Fetch full url by short url hash from data storage.
     *
     * @param string $shortUrl
     *
     * @return null|string
     */
    private function expand(string $shortUrl):?string
    {
        $fullUrl = $this->dataProvider->get($shortUrl);
        if (null === $fullUrl) {
            $this->triggerEvent(self::EVENT_EXPAND_FAIL, compact('shortUrl'));
            return null;
        }

        $this->triggerEvent(self::EVENT_AFTER_EXPAND, compact('shortUrl', 'fullUrl'));
        return $fullUrl;
    }

    /**
     * Create short url.
     *
     * @param string      $fullUrl full url to be shorten.
     * @param string|null $hash    if provided $hash value will be user as short url hash.
     *
     * @throws InvalidConfigException if UrlShortener.baseUrl or App.fullBaseUrl not defined.
     * @throws DuplicateKeyException
     * @return string
     */
    public function shorten(string $fullUrl, string $hash = null): string
    {
        $this->triggerEvent(self::EVENT_BEFORE_SHORTEN, compact('fullUrl'));
        $shortUrlHash = $hash ?? $this->generateShortUrlHash($fullUrl);

        try {
            $this->dataProvider->put($shortUrlHash, $fullUrl);
        } catch (DuplicateKeyException $e) {
            if (null === $hash && true === $this->getConfig(self::CONFIG_RETRY_ON_DUPLICATE_KEY)) {
                return $this->shorten($fullUrl);
            }

            $this->triggerEvent(self::EVENT_SHORTEN_FAIL, compact('shortUrlHash'));
            throw $e;
        }

        $shortUrl     = $this->getBaseUrl();
        $shortUrlPath = $this->getConfig(self::CONFIG_SHORT_URL_PATH_KEY);
        if (null !== $shortUrlPath) {
            $shortUrl .= '/' . $shortUrlPath;
        }
        $shortUrl .= '/' . $shortUrlHash;
        $this->triggerEvent(self::EVENT_AFTER_SHORTEN, compact('fullUrl', 'shortUrl'));
        return $shortUrl;
    }

    /**
     * Read and return base url from plugin config.
     * If baseUrl not defined fullBaseUrl from  app config will be used.
     *
     * @throws InvalidConfigException if UrlShortener.baseUrl or App.fullBaseUrl not defined.
     * @return string
     */
    private function getBaseUrl(): string
    {
        $baseUrl = $this->getConfig(self::CONFIG_BASE_URL_KEY);
        if (!$baseUrl) {
            $baseUrl = Configure::read('App.fullBaseUrl');
            if (!$baseUrl) {
                throw new InvalidConfigException('You must define \'UrlShortener.baseUrl\' or \'App.fullBaseUrl\' in the App config');
            }
        }

        $baseUrl = HttpUri::createFromString($baseUrl)->__toString();
        $baseUrl = preg_replace('/\/$/', '', $baseUrl);
        return $baseUrl;
    }

    /**
     * Generate short ulr.
     *
     * @param string $fullUrl full url to be shorten.
     *
     * @return string
     */
    private function generateShortUrlHash(string $fullUrl): string
    {
        if (null !== $this->hashGenerator) {
            $hash = call_user_func($this->hashGenerator, $fullUrl);
        } else {
            $hash = $this->defaultHashGenerator($fullUrl);
        }

        return substr($hash, 0, $this->getConfig(self::CONFIG_SHORT_ULR_LENGTH_KEY));
    }

    /**
     * Default short url hash generator. Will be invoked if $shortUrlGenerator not set.
     *
     * @param string $fullUrl full url to be shorten.
     *
     * @return string
     */
    private function defaultHashGenerator(string $fullUrl): string
    {
        $hash = hash('sha512', uniqid($fullUrl, true));
        return preg_replace('/[^a-z]/i', '', base64_encode($hash));
    }

    /**
     * Create a new Event instance and dispatch it.
     *
     * @param string $eventName event name.
     * @param array  $data      event data which will be attached to Event.
     *
     * @return void
     */
    private function triggerEvent(string $eventName, array $data = []): void
    {
        $event = new Event($eventName, $this, $data);
        $this->eventManager()->dispatch($event);
    }
}
