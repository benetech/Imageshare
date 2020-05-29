<?php

namespace Imageshare\Controllers;

require_once imageshare_php_file('classes/class.logger.php');
require_once imageshare_php_file('classes/models/class.resource_collection.php');

use Imageshare\Logger;
use ImageShare\Models\ResourceCollection as Model;

class ResourceCollection {

    public function __construct() {
    }

    public function get_featured_collections($number = 5) {
        return Model::get_featured($number);
    }
}
