
<?php

/**
 * logger.
 *
 * @package local_categories_domains
 */

defined('MOODLE_INTERNAL') || die();

class request_exception extends moodle_exception
{
    private int $requestcode;
    public function __construct(string $errorcode, int $requestcode = 500)
    {
        parent::__construct($errorcode);
        $this->requestcode = $requestcode;
    }
    public function getRequestcode(): ?int
    {
        return $this->requestcode;
    }
}
