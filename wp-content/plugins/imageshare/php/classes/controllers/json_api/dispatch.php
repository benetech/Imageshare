<?php
    require_once imageshare_php_file('classes/controllers/json_api/class.types.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.subjects.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.accommodations.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.sources.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.collections.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.resources.php');
    
    use Imageshare\Logger;

    use Imageshare\Controllers\JSONAPI\Types as TypesController;
    use Imageshare\Controllers\JSONAPI\Subjects as SubjectsController;
    use Imageshare\Controllers\JSONAPI\Accommodations as AccommodationsController;
    use Imageshare\Controllers\JSONAPI\Sources as SourcesController;
    use Imageshare\Controllers\JSONAPI\Collections as CollectionsController;
    use Imageshare\Controllers\JSONAPI\Resources as ResourcesController;

    $controller = get_query_var('btis_api');

    $id = get_query_var('btis_api_id');
    $relationship = get_query_var('btis_api_relationship');

    Logger::log([$controller, $id, $relationship]);

    switch ($controller) {
        case 'types':
            TypesController::render();
            break;

        case 'subjects':
            SubjectsController::render([
                'id' => $id,
                'relationship' => $relationship
            ]);
            break;

        case 'accommodations':
            AccommodationsController::render();
            break;

        case 'sources':
            SourcesController::render();
            break;

        case 'collections':
            CollectionsController::render();
            break;

        case 'resources':
            ResourcesController::render();
            break;

    }
