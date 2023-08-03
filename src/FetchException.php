<?php

namespace Utopia\Fetch;

class FetchException extends \Exception
{
    /**
     * Constructor
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message); // Call the parent constructor
    }
    public function __toString()
    {
        return __CLASS__ . " {$this->message}\n"; // Return the class name, code and message
    }
}
