<?php

declare(strict_types=1);

/**
 * Bootstrap for integration tests.
 *
 * Defines WordPress function stubs so the Plugin class can be tested without
 * a full WordPress installation.
 */

// Load Composer autoloader.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// -------------------------------------------------------------------
// WordPress function stubs
// -------------------------------------------------------------------

if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

/** @var array<string, mixed> Simulated wp_options storage. */
$GLOBALS['_vcip_test_options'] = [];

/** @var array<string, array{callback: callable, args: list<mixed>}> Last applied filter info. */
$GLOBALS['_vcip_test_filters'] = [];

/** @var array<string, list<list<mixed>>> Last fired action info. */
$GLOBALS['_vcip_test_actions'] = [];

if (! function_exists('get_option')) {
    /**
     * @param mixed $default
     * @return mixed
     */
    function get_option(string $option, $default = false)
    {
        return $GLOBALS['_vcip_test_options'][$option] ?? $default;
    }
}

if (! function_exists('update_option')) {
    /**
     * @param mixed $value
     */
    function update_option(string $option, $value): bool
    {
        $GLOBALS['_vcip_test_options'][$option] = $value;
        return true;
    }
}

if (! function_exists('apply_filters')) {
    /**
     * @param mixed ...$args
     * @return mixed
     */
    function apply_filters(string $hook_name, ...$args)
    {
        $GLOBALS['_vcip_test_filters'][$hook_name] = $args;

        // Return the first argument unchanged (default behaviour).
        return $args[0] ?? null;
    }
}

if (! function_exists('do_action')) {
    /**
     * @param mixed ...$args
     */
    function do_action(string $hook_name, ...$args): void
    {
        $GLOBALS['_vcip_test_actions'][$hook_name][] = $args;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}
