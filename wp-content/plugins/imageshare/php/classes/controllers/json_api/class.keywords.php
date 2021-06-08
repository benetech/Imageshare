<?php

namespace Imageshare\Controllers\JSONAPI;

use Imageshare\Models\Resource as ResourceModel;

class Keywords extends Base {
    const name = 'keyword';
    const plural_name = 'keywords';

    public static function render() {
        $stopwords = json_decode(file_get_contents(imageshare_asset_file('stopwords.json')));

        $keywords = array_values(array_filter(ResourceModel::get_keywords(), function ($keyword) use ($stopwords) {
            return !in_array($keyword, $stopwords);
        }));

        return parent::render_response($keywords);
    }
}
