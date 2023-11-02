<?php
namespace Mittwald\Deployer;

use Exception;
use function Deployer\get;

/**
 * This is a type-safe wrapper function around Deployer's `get`
 * function, so that Psalm doesn't complain that much.
 *
 * @param string $key
 * @return string
 * @throws Exception
 */
function get_str(string $key): string
{
    $value = get($key);
    if (!is_string($value)) {
        throw new Exception("{$key} is not a string");
    }

    return $value;
}

/**
 * This is a type-safe wrapper function around Deployer's `get`
 * function, so that Psalm doesn't complain that much.
 *
 * @param string $key
 * @return string|null
 * @throws Exception
 */
function get_str_nullable(string $key): string|null
{
    $value = get($key);
    if (!is_string($value) && !is_null($value)) {
        throw new Exception("{$key} is not a string");
    }

    return $value;
}

/**
 * This is a type-safe wrapper function around Deployer's `get`
 * function, so that Psalm doesn't complain that much.
 *
 * @param string $key
 * @param array $default
 * @return array
 * @throws Exception
 */
function get_array(string $key, array $default = []): array
{
    $value = get($key, $default);
    if (!is_array($value)) {
        throw new Exception("{$key} is not a string");
    }

    return $value;
}