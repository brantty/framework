<?php
/**
 * This file is part of Notadd.
 *
 * @author TwilRoad <269044570@qq.com>
 * @copyright (c) 2016, iBenchu.org
 * @datetime 2016-11-30 17:42
 */
namespace Notadd\Foundation\Configuration\Traits;

/**
 * Class KeyParser.
 */
trait KeyParser
{
    /**
     * @var array
     */
    protected $keyParserCache = [];

    /**
     * TODO: Method setParsedKey Description
     *
     * @param string $key
     * @param array  $parsed
     *
     * @return void
     */
    public function setParsedKey($key, $parsed)
    {
        $this->keyParserCache[$key] = $parsed;
    }

    /**
     * TODO: Method parseKey Description
     *
     * @param string $key
     *
     * @return array
     */
    public function parseKey($key)
    {
        if (isset($this->keyParserCache[$key])) {
            return $this->keyParserCache[$key];
        }
        $segments = explode('.', $key);
        if (strpos($key, '::') === false) {
            $parsed = $this->keyParserParseBasicSegments($segments);
        } else {
            $parsed = $this->keyParserParseSegments($key);
        }
        return $this->keyParserCache[$key] = $parsed;
    }

    /**
     * TODO: Method keyParserParseBasicSegments Description
     *
     * @param array $segments
     *
     * @return array
     */
    protected function keyParserParseBasicSegments(array $segments)
    {
        $group = $segments[0];
        if (count($segments) == 1) {
            return [null, $group, null];
        } else {
            $item = implode('.', array_slice($segments, 1));

            return [null, $group, $item];
        }
    }

    /**
     * TODO: Method keyParserParseSegments Description
     *
     * @param string $key
     *
     * @return array
     */
    protected function keyParserParseSegments($key)
    {
        list($namespace, $item) = explode('::', $key);
        $itemSegments = explode('.', $item);
        $groupAndItem = array_slice($this->keyParserParseBasicSegments($itemSegments), 1);

        return array_merge([$namespace], $groupAndItem);
    }
}