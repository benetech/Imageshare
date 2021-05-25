<?php

namespace Imageshare\Controllers\JSONAPI;

use Imageshare\Models\Resource as ResourceModel;

class Keywords extends Base {
    const name = 'keyword';
    const plural_name = 'keywords';

    public static function render() {
        $keywords = ResourceModel::get_keywords();

        return parent::render_response($keywords);
    }
}
