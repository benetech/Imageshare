<?php
    require_once imageshare_php_file('classes/controllers/json_api/class.types.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.subjects.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.accommodations.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.sources.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.collections.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.resources.php');
    require_once imageshare_php_file('classes/controllers/json_api/class.tags.php');
    
    use Imageshare\Logger;

    use Imageshare\Controllers\JSONAPI\Types as TypesController;
    use Imageshare\Controllers\JSONAPI\Subjects as SubjectsController;
    use Imageshare\Controllers\JSONAPI\Accommodations as AccommodationsController;
    use Imageshare\Controllers\JSONAPI\Sources as SourcesController;
    use Imageshare\Controllers\JSONAPI\Collections as CollectionsController;
    use Imageshare\Controllers\JSONAPI\Resources as ResourcesController;
    use Imageshare\Controllers\JSONAPI\Tags as TagsController;

    $controller = get_query_var('btis_api');

    $id = get_query_var('btis_api_id');
    $relationship = get_query_var('btis_api_relationship');
    $is_search = get_query_var('btis_api_search');

    if ($is_search && in_array($controller, ['resources', 'collections'])) {
        $params = ($_GET);
        return $controller === 'resources' ? ResourcesController::search($params) : CollectionsController::search($params);
    }

    $args = [
        'id' => $id,
        'relationship' => $relationship
    ];

    switch ($controller) {
        case 'types':
            TypesController::render($args);
            break;

        case 'subjects':
            SubjectsController::render($args);
            break;

        case 'accommodations':
            AccommodationsController::render($args);
            break;

        case 'sources':
            SourcesController::render();
            break;

        case 'collections':
            CollectionsController::render($args);
            break;

        case 'resources':
            ResourcesController::render($args);
            break;

        case 'keywords':
            TagsController::render();
            break;

    }
