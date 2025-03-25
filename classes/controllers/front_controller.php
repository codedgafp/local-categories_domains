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
use ReflectionClass;
use ReflectionException;
use request_exception;
use webservice;
use webservice_access_exception;
use stdClass;

require_once "$CFG->dirroot/webservice/lib.php";

/**
 * Class front_controller
 */
class front_controller
{
    /**
     * @var array|null
     */
    protected ?array $params = [];

    /**
     * @var string
     */
    protected string $controller;

    /**
     * @var string
     */
    protected string $action;

    /**
     * @var string namespace of the plugin using the front controller
     */
    protected string $namespace;

    /**
     * @var string plugin using the front_controller
     */
    protected string $plugin;

    /**
     * @var string plugin type using the front_controller
     */
    protected string $plugintype;

    /**
     * front_controller constructor.
     *
     * @param string $plugin local plugin using the front_controller ex : user, session...
     * @param string $namespace namespace of the plugin using the front controller
     * @param array|null $options
     * @throws ReflectionException
     * @throws moodle_exception
     */
    public function __construct(string $plugin, string $namespace, ?array $options = null)
    {
        $this->namespace = $namespace;
        $this->plugin = $plugin;

        if (!empty($options)) {
            $this->params = $options;
        } else {
            $this->set_params();
        }

        $this->plugintype = $this->params['plugintype'] ?? "local";

        if (isset($this->params['controller'])) {
            $this->set_controller($this->params['controller']);
        }

        if (isset($this->params['action'])) {
            $this->set_action($this->params['action']);
        }
    }

    /**
     * Set controller
     *
     * @param string $controller
     * @return $this
     * @throws moodle_exception
     */
    public function set_controller(string $controller): front_controller
    {
        global $CFG;

        /** @var string $controllerurl */
        $controllerurl = $CFG->dirroot . '/' . $this->plugintype . '/' . $this->plugin . '/classes/controllers/' . $controller .
            '_controller.php';

        if (!file_exists($controllerurl)) {
            logger::error(
                "[local_categories_domains@front_controller::set_controller]",
                "Controller not found $controller not found"
            );
        }

        require_once $controllerurl;

        /** @var string $controller */
        $controller = strtolower($controller) . "_controller";

        if (!class_exists($this->namespace . $controller)) {
            logger::error(
                "[local_categories_domains@front_controller::set_controller]",
                "'$controller' does not exist"
            );
        }

        $this->controller = $controller;

        return $this;
    }

    /**
     * Set action to call
     *
     * @param string $action
     * @return $this
     * @throws ReflectionException
     */
    public function set_action(string $action): front_controller
    {
        /** @var ReflectionClass $reflector */
        $reflector = new ReflectionClass($this->namespace . $this->controller);

        if (!$reflector->hasMethod($action)) {
            logger::error(
                "[local_categories_domains@front_controller::set_action]",
                "action '$action' of '$this->controller' does not exist"
            );
        }

        $this->action = $action;

        return $this;

    }

    /**
     * Set params from $_GET and $_POST and Raw json
     */
    public function set_params()
    {
        /** @var array|false|null $get */
        $get = filter_input_array(INPUT_GET);

        /** @var array|false|null $post */
        $post = filter_input_array(INPUT_POST);

        // Get data from raw json
        /** @var string|false $jsonData */
        $jsonData = file_get_contents('php://input');

        /** @var array|null $json */
        $json = json_decode($jsonData, true);

        $this->params = array_merge((array) $get, (array) $post, (array) $json);
    }

    /**
     * Execute the controller action
     * @return array
     * @throws request_exception | webservice_access_exception | moodle_exception
     */
    public function run(): array|stdClass|bool
    {
        /** @var string $class */
        $class = $this->namespace . $this->controller;

        /** @var controller_base $class */
        $controller = new $class($this->params);

        if (isset($this->params['token'])) {
            /** @var webservice $class */
            $webservice = new webservice();
            $webservice->authenticate_user($this->params['token']);
        }

        /** @var string $action */
        $action = $controller->get_param('action');
        if (!method_exists($controller, $action)) {
            logger::error(
                "[local_categories_domains@front_controller::run]",
                "action '$action' of '$this->controller' does not exist"
            );
        }

        $callbackToReturn = call_user_func([$controller, $action]);

        return $callbackToReturn;
    }

}
