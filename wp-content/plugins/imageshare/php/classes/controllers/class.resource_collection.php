<?php

namespace Imageshare\Controllers;

require_once imageshare_php_file('classes/class.logger.php');
require_once imageshare_php_file('classes/models/class.resource_collection.php');

use Imageshare\Logger;
use ImageShare\Models\ResourceCollection as Model;

class ResourceCollection {

    public function __construct() {
    }

    public function get_popular_categories($number = 5) {
        return array();
    }
}
