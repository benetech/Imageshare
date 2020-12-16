<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

class Subjects extends Taxonomy {
    const taxonomy = 'subjects';
    const name = 'subject';
    const plural_name = 'subjects';
}
