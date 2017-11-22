<?php

namespace UrlShortener\Exception;

class DuplicateKeyException extends \Exception
{
    /**
     * @var string
     */
    protected $key;

    public function __construct($key)
    {
        $this->key = $key;
        parent::__construct('Unable to put new value into the storage. Key: ' . $key . ' is\'t unique');
    }

    public function getKey(): string
    {
        return $this->key;
    }
}