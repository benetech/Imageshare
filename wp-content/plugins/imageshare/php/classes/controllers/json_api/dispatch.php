<?php
    require_once imageshare_php_file('classes/controllers/json_api/class.types.php');

    use Imageshare\Controllers\JSONAPI\Types as TypesController;

    $controller = get_query_var('btis_api');

    switch ($controller) {
        case 'types':
            TypesController::render();
            break;
    }
