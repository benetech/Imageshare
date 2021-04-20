CREATE TABLE IF NOT EXISTS %resource_group_join_table_name% (
    `resource_post_id` bigint(20) UNSIGNED NOT NULL,
    `group_post_id` bigint(20) UNSIGNED NOT NULL,
    `is_default_group` BOOLEAN DEFAULT 0,
    PRIMARY KEY (`resource_post_id`, `group_post_id`)
) %charset_collate%;

CREATE TABLE IF NOT EXISTS %group_resource_file_join_table_name% (
    `group_post_id` bigint(20) UNSIGNED NOT NULL,
    `resource_file_post_id` bigint(20) UNSIGNED NOT NULL,
    PRIMARY KEY (`group_post_id`, `resource_file_post_id`)
) %charset_collate%;
