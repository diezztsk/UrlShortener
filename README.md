# UrlShortener plugin for CakePHP

This plugin allow to shorten long urls. For example you have a long url like this  
`http://some-domain.com/events/follow?event_id=3D8e296a067a37563370ded05f5a3bf3ec&refer=IuyNqrcLQaqPhjzhFiCARg__.3600
.1282755600-761405628%26fb_sig_ss%3DigFqJKrhJZWGSRO`  
This plugin can shorten this url to  
`http://some-domain.com/NjdhMz`  
and after user follow this short url plugin will redirect you to original url.

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require your-name-here/UrlShortener
```

## Note

This package has DataProviderInterface dependency which implementation is not provided. You 
should implement `DataProviderInterface` in your 
source and provide it in the App config file.
You may use any external storage that you like: Redis, Mongo, MySql, files and so on.
In order to prevent collisions `DataProviderInterface::put()` method should throw `UrlShortener\DuplicateKeyException` if presented key is not unique.

## Configuration

Define next section in the App config file
```php
'UrlShortener' => [
    // Requeried. Data Provider Implementation.
    // Full class name should be provided.
    'dataProveder' => YourImplementation::class,
    // Optional. Short url hash length. By default 6
    'urlLength' => 6,
    // Optional. Whether retry if catch DuplicateKeyException
    // By default false
    'retryOnDuplicate' => false,
    //Oprioanal. Base url of short url.  
    // If this param not set App.appBaseUrl will be used as baseUrl
    'baseUrl' => 'http://some-domain.com
    // Optional. Short url path. By default null,
    // This value will be inserted between base url and short url hash
    // and short url will be looking like this http://domain.com/l/MnNQLC
    'shortUrlPath' => 'l',
]
```

at the end of  `config/bootstrap.php` write next lines:
```php
    Plugin::load('UrlShortener', ['bootstrap' => false, 'routes' => true]);
```

## Usage

Create short url
```php
    $urlShortener = new UrlShortener();
    $shortUrl = $urlShortener->shorten('https://domain.com/some_mega_supper_pupper_long_url');
    // $shortUlr = 'http://domain.com/l/MnNQLC'
```
You can set manually short url
```php
    $shortUrl = $urlShortener->shorten('https://domain.com/some_mega_supper_pupper_long_url', 'one');
    // $shortUlr = 'http://domain.com/l/one'
```

Plugin is providing two methods for fetching full url.
```php    
    //fetch full url in controller
    $fullUrl = $urlShortener->fetchByRequest($this->request);
    
    //fetch url anywhere
    $fullUrl = $urlShortener->fetchByHash($shortUrlHash);
```    

Plugin contain Facade class for convenient UrlShortener usage 
```php
    use UrlShortener/Facade as UrlShortener;
    
    //create short url
    $shortUrl = UrlShortener::shorten('https://domain.com/some_mega_supper_pupper_long_url');
    
    //fetch full url
    $fullUrl = UrlShortener::fetchByRequest($this->request);
```

Plugin have default short url hash generator but you may define custom generator by passing callback into 
`setHashGenerator` method.
```php
$urlShortener = new UrlShortener();
$urlShortener->setHashGenerator(function($fullUrl) {
    return uniqid($fullUrl);
});
```
Other way to set user defined generator is to define it in the UrlShortener config
```php
//Define some class in your sorce
class Generator {
    public static generate(string $fullUrl): string
    {
        return uniqid($fullUrl);
    }
}

//app config
'UrlShortener' => [
    ...
    'hashGenerator' => [Generator::class, 'generate']
]
``` 

**UrlShortener** support next events:
  - `url.shortener.event.before.expand`
  - `url.shortener.event.after.expand`
  - `url.shortener.event.expand.fail`
  - `url.shortener.event.before.shorten`
  - `url.shortener.event.after.shorten`
  - `url.shortener.event.shorten.fail`
  
You can set event listeners for each event for extending plugin functionality for example logging or counting hits.
```php
EventManager::instance()->on(UrlShortener::EVENT_EXPAND_FAIL, function(Event $event) {
    $shortUrl = $event->data['shortUrl'];
    Log::write('error', 'Unable to fetch url: ' . $shortUrl);
});
```