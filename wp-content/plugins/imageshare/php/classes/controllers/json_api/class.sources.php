<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

class Sources extends Base {
    public static function normalise_id($source) {
        return strtolower(
            preg_replace('/_{2,}/', '_',
            preg_replace('/[^\w]+/', '_',
            preg_replace('/\s+/', '_',
                $source
        ))));
    }

    public static function render() {
        global $imageshare;
        $text_sources = $imageshare->controllers->search->get_available_sources();

        $sources = array_reduce($text_sources, function ($list, $source) {
            $list[] = [
                'type' => 'resource_source',
                'id' => self::normalise_id($source),
                'attributes' => [
                    'name' => $source
                ]
            ];

            return $list;
        }, []);

        return parent::render_response($sources);
    }
}
