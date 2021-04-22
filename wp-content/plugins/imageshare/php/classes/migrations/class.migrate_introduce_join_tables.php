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

        self::create_join_tables();

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
            'fields'      => 'ids',
        ));

        $batch_size = count($batch);

        foreach ($batch as $resource_id) {
            $group_ids = self::get_resource_groups($resource_id);

            foreach ($group_ids as $group_id) {
                $is_default = get_post_meta($group_id, 'is_default', true) ?? false;
                DB::add_resource_group_relationship($resource_id, $group_id, $is_default);
                $file_ids = self::get_group_resource_files($group_id);
                foreach ($file_ids as $file_id) {
                    DB::add_group_resource_file_relationship($group_id, $file_id);
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

    public static function get_resource_groups($resource_id) {
        $args = [
            'numberposts'   => -1,
            'post_type'     => [ResourceFileGroup::type],
            'meta_key' => 'parent_resource',
            'meta_value' => (string) $resource_id,
            'fields' => 'ids'
        ];

        return get_posts($args);
    }

    public static function get_group_resource_files($group_id) {
        $file_ids = get_post_meta($group_id, 'files', true);

        if (!is_array($file_ids)) {
            return [];
        }

        return $file_ids;
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
        foreach (['create_resource_group_join_table.sql', 'create_group_resource_file_join_table.sql'] as $file) {
            $path = imageshare_sql_file($file);
            $file_sql = file_get_contents($path);
            $sql = self::replace_sql_variables($file_sql);
            self::run_sql_query($sql);
        }
    }
}
