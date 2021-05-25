<?php
    use Imageshare\Logger;

    use Imageshare\Controllers\JSONAPI\Types as TypesController;
    use Imageshare\Controllers\JSONAPI\Formats as FormatsController;
    use Imageshare\Controllers\JSONAPI\Subjects as SubjectsController;
    use Imageshare\Controllers\JSONAPI\Accommodations as AccommodationsController;
    use Imageshare\Controllers\JSONAPI\Sources as SourcesController;
    use Imageshare\Controllers\JSONAPI\Collections as CollectionsController;
    use Imageshare\Controllers\JSONAPI\Resources as ResourcesController;
    use Imageshare\Controllers\JSONAPI\Tags as TagsController;
    use Imageshare\Controllers\JSONAPI\Keywords as KeywordsController;

    $controller = get_query_var('btis_api');

    $id = get_query_var('btis_api_id');
    $page = get_query_var('btis_api_page');
    $relationship = get_query_var('btis_api_relationship');
    $is_search = get_query_var('btis_api_search');

    if ($is_search && in_array($controller, ['resources', 'collections'])) {
        $params = ($_GET);
        return $controller === 'resources' ? ResourcesController::search($params) : CollectionsController::search($params);
    }

    $args = [
        'id' => $id,
        'page' => $page,
        'relationship' => $relationship
    ];

    // Set up CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Request-Method: GET, OPTIONS');
    header('Access-Control-Request-Headers: Content-Type');

    switch ($controller) {
        case 'types':
            TypesController::render($args);
            break;

        case 'formats':
            FormatsController::render($args);
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

        case 'tags':
            TagsController::render();
            break;

        case 'terms':
            KeywordsController::render();
            break;
    }
