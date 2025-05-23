<?php

/**
 * logger.
 *
 * @package local_categories_domains
 */

namespace local_categories_domains\utils;

use local_categories_domains\exception\request_exception;

defined('MOODLE_INTERNAL') || die();

class logger
{
    /**
     * log and throw error
     *
     * @param string $origin_flag
     * @param string $message
     * @param string $log_message
     * @param string $log_stacktrace
     * @param int $code
     * @return void
     * @throws request_exception
     */
    public static function error(
        string $origin_flag,
        string $message,
        string $log_message = "",
        string $log_stacktrace = "",
        int $code = 500
    ): void {
        error_log("$origin_flag $message $log_message $log_stacktrace");
        throw new request_exception($message, $code);
    }
}
