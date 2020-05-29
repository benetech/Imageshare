<?php

namespace Imageshare\Controllers;

use Imageshare\Logger;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFile as ResourceFileModel;

class Search {

    public function __construct() {
    }

    public static function get_available_terms() {
        return [
            'subjects'       => ResourceModel::available_subjects(),
            'accommodations' => ResourceFileModel::available_accessibility_accommodations(),
            'types'          => ResourceFileModel::available_types()
        ];
    }

    public function query($args) {

    }
}
