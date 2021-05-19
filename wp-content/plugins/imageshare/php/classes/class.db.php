<?php

namespace Imageshare;

use Imageshare\Logger;

class DB {

    public static function setup() {
        global $wpdb;

        !defined('IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME') && define('IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME', $wpdb->prefix . 'btis_resource_group_jt'); 
        !defined('IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME') && define('IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME', $wpdb->prefix . 'btis_group_resource_file_jt');
    }

    public static function add_resource_group_relationship($resource_id, $group_id, $is_default) {
        global $wpdb;

        DB::setup();
        
        $record = [
            'resource_post_id' => $resource_id,
            'group_post_id' => $group_id,
            'is_default_group' => $is_default
        ];

        $data_types = ["%d", "%d", "%d"];

        $wpdb->replace(IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME, $record, $data_types);
    }

    public static function add_group_resource_file_relationship($group_id, $file_id) {
        global $wpdb;
        
        DB::setup();

        $record = [
            'group_post_id' => $group_id,
            'resource_file_post_id' => $file_id,
        ];

        $data_types = ["%d", "%d"];

        $wpdb->replace(IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME, $record, $data_types);
    }

    public static function get_resources_containing_file($resource_file_id) {
        global $wpdb;

        DB::setup();

        $rgjt = IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME;
        $gfjt = IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME;

        $sql = "select rgjt.resource_post_id from {$rgjt} rgjt inner join {$gfjt} gfjt on gfjt.group_post_id = rgjt.group_post_id where gfjt.resource_file_post_id = %d";

        return $wpdb->get_col($wpdb->prepare($sql, $resource_file_id));
    }

    public static function get_resource_file_ids($resource_id) {
        global $wpdb;

        DB::setup();

        $rgjt = IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME;
        $gfjt = IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME;

        $sql = "select gfjt.resource_file_post_id from {$rgjt} rgjt inner join {$gfjt} gfjt on gfjt.group_post_id = rgjt.group_post_id where rgjt.resource_post_id = %d";

        return $wpdb->get_col($wpdb->prepare($sql, $resource_id));
    }

    public static function get_resource_group_ids($resource_id) {
        global $wpdb;

        DB::setup();

        $rgjt = IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME;
        $gfjt = IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME;

        $sql = "select distinct rgjt.group_post_id from {$rgjt} rgjt where rgjt.resource_post_id = %d";

        return $wpdb->get_col($wpdb->prepare($sql, $resource_id));
    }

    public static function get_group_containing_resource_file($resource_file_id) {
        global $wpdb;

        DB::setup();

        $gfjt = IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME;

        $sql = "select gfjt.group_post_id from {$gfjt} gfjt where gfjt.resource_file_post_id = %d";

        return $wpdb->get_var($wpdb->prepare($sql, $resource_file_id));
    }

    public static function get_resource_default_group($resource_id) {
        global $wpdb;

        DB::setup();

        $rgjt = IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME;

        $sql = "select rgjt.group_post_id from {$rgjt} rgjt where rgjt.resource_post_id = %d and is_default_group=1";

        return $wpdb->get_var($wpdb->prepare($sql, $resource_id));
    }

    public static function get_resource_group_file_ids($resource_group_id) {
        global $wpdb;

        DB::setup();

        $gfjt = IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME;

        $sql = "select gfjt.resource_file_post_id from {$gfjt} gfjt where gfjt.group_post_id = %d";

        return $wpdb->get_col($wpdb->prepare($sql, $resource_group_id));
    }

    public static function remove_resource_entries($resource_id) {
        global $wpdb;

        DB::setup();

        $rgjt = IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME;

        $wpdb->delete($rgjt, ['resource_post_id' => $resource_id], ['%d']);
    }

    public static function remove_group_entries($group_id) {
        global $wpdb;

        DB::setup();

        $gfjt = IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME;
        $rgjt = IMAGESHARE_RESOURCE_GROUP_JOIN_TABLE_NAME;

        $wpdb->delete($gfjt, ['group_post_id' => $group_id], ['%d']);
        $wpdb->delete($rgjt, ['group_post_id' => $group_id], ['%d']);
    }

    public static function remove_resource_file_entries($resource_file_id) {
        global $wpdb;

        DB::setup();

        $gfjt = IMAGESHARE_GROUP_RESOURCE_FILE_JOIN_TABLE_NAME;

        $wpdb->delete($gfjt, ['resource_file_post_id' => $resource_file_id], ['%d']);
    }
}
