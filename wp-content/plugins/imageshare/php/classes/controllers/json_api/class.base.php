<?php

namespace Imageshare\Controllers\JSONAPI;

class Base {
    public static function render_response($data) {
        header('Content-Type: vnd.api+json');
        echo json_encode(['data' => $data], JSON_PRETTY_PRINT);
    }
}
