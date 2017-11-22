<?php

namespace UrlShortener;

use UrlShortener\Exception\DuplicateKeyException;

/**
 * Class AbstractUrlShorterDriver
 *
 * @package UrlShortener
 */
interface DataProviderInterface
{
    /**
     * Put value into external storage.
     *
     * @param string $key
     * @param string $value
     *
     * @throws DuplicateKeyException if key is not unique.
     * @return void
     */
    public function put(string $key, string $value): void;

    /**
     * Return value by key. If value is missing or expired method will return null.
     *
     * @param string $key
     *
     * @return null|string
     */
    public function get(string $key): ?string;
}
