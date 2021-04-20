<?php

namespace Imageshare\Migrations;

use Imageshare\Logger;
use Imageshare\DB;
use Imageshare\Models\Resource;
use Imageshare\Models\ResourceFileGroup;

class MigrateIntroduceJoinTables {
    /*
     * This migration:
     *   - creates join tables
     *   - populates with existing data
     */

    public static function ajax_migrate_introduce_join_tables() {
        DB::setup();

        if (is_null(get_option('imageshare_join_tables_created', null))) {
            self::create_join_tables();
            add_option('imageshare_join_tables_created', true);
        }

        $offset = intval(isset($_POST['offset']) ? $_POST['offset'] : 0);
        $fixed = intval(isset($_POST['fixed']) ? $_POST['fixed'] : 0);
        $errors = intval(isset($_POST['errors']) ? $_POST['errors'] : 0);
        $size = intval(isset($_POST['size']) ? $_POST['size'] : 50);

        echo json_encode(self::process_resources($offset, $fixed, $errors, $size));

        return wp_die();
    }

    public static function process_resources($offset, $fixed, $errors, $size) {
        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => $size,
            'post_type'   => [Resource::type],
            'post_status' => ['publish', 'pending', 'draft'],
            'post_parent' => null,
        ));

        $batch_size = count($batch);

        foreach ($batch as $post) {
            $resource = Resource::from_post($post);
            $groups = $resource->groups();

            foreach ($groups as $group) {
                DB::add_resource_group_relationship($resource->id, $group->id, $group->is_default_for_parent());
                $files = $group->files();
                foreach ($files as $file) {
                    DB::add_group_resource_file_relationship($group->id, $file->id);
                }
            }
        }

        return [ 
            'size' => $batch_size,
            'offset' => $offset + $batch_size,
            'fixed' => $fixed,
            'errors' => $errors
        ];
    }

    public static function replace_sql_variables(string $sql) {
        global $wpdb;

        $search_strings = [
            '%resource_group_join_table_name%',
            '%group_resource_file_join_table_name%',
            '%charset_collate%'
        ];

        $replace_strings = [
            IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME,
            IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME,
            $wpdb->get_charset_collate()
        ];

        $sql = str_replace($search_strings, $replace_strings, $sql);

        return $sql;
    }

    public static function run_sql_query(string $sql) {
        global $wpdb;
        $wpdb->query($sql);
    }

    public static function create_join_tables() {
        $path = imageshare_sql_file('create_tables.sql');
        $file_sql = file_get_contents($path);
        $sql = self::replace_sql_variables($file_sql);
        self::run_sql_query($sql);
    }
}
