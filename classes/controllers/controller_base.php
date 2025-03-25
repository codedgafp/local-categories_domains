<?php

/**
 * Abstract controller class
 * A page is a course section with some other attributes
 *
 * @package local_categories_domains
 */

namespace local_categories_domains\controllers;

use \local_categories_domains\utils\logger;

use moodle_exception;

/**
 * Class controller_base
 *
 * @package local_enrollment_service
 */
abstract class controller_base
{
    /**
     * @var array
     */
    protected array $params = [];

    /**
     * controller_base constructor.
     *
     * @param $params
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * @throws moodle_exception
     */
    public function get_required_param($paramname, $type = null, $default = null)
    {
        if (!isset($this->params[$paramname]) && !$default)
            logger::error(
                "[local_categories_domains@controller_base::get_required_param]",
                "'$paramname' missing"
            );
        return self::get_param($paramname, $type, $default);
    }

    /**
     * Get request param
     *
     * @param string $paramname
     * @param string|null $type default null if the type is not important
     * @param mixed $default default value if the param does not exist
     * @return mixed value of the param (or default value)
     * @throws moodle_exception
     */
    public function get_param(string $paramname, ?string $type = null, $default = false)
    {
        if (isset($this->params[$paramname])) {
            /** @var mixed $class */
            $param = $this->params[$paramname];

            if (!empty($type)) {
                switch ($type) {
                    case PARAM_INT:
                        if (!is_integer($param) && !ctype_digit($param)) {
                            logger::error(
                                "[local_categories_domains@controller_base::get_param]",
                                "'$paramname' must be an integer"
                            );
                        }
                        $param = (int) $param;
                        break;
                    // Add cases for new types here.
                    default:
                        is_array($param) ? clean_param_array($param, $type, true) : clean_param($param, $type);
                        break;
                }
            }
            return $param;
        }

        return $default;

    }

    /**
     * Success message former
     *
     * @param array $additional
     * @param string|null $message
     * @return array
     */
    public function success(array $additional = [], ?string $message = null): array
    {
        return array_merge(
            ['success' => true],
            $message ? ['message' => $message] : [],
            $additional
        );
    }

    /**
     * Error message former
     *
     * @param string|null $message
     * @return array
     */
    public function error(?string $message = null): array
    {
        return array_merge(
            ['success' => false],
            $message ? ['message' => $message] : []
        );
    }
}
