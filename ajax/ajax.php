<?php

/**
 * Ajax request dispatcher
 *
 * @package local_categories_domains
 */


use local_categories_domains\controllers\front_controller;

require_once __DIR__ . '/../../../config.php';

// Require login.
require_login();

// Redirection to the login page if the user does not login.
if (!isloggedin()) {
    redirect($CFG->wwwroot . '/login/index.php');
}

try {
    // Call front controller.
    $frontcontroller = new front_controller('categories_domains', 'local_categories_domains\\controllers\\');

    // Call the controller method, result is json.
    echo json_encode($frontcontroller->run());
} catch (Exception|request_exception $e) {
    http_response_code(property_exists($e, 'requestcode') ? $e->getRequestcode() : 500);

    echo json_encode([
        'message' => $e->getMessage(),
        'success' => FALSE,
    ]);
}
