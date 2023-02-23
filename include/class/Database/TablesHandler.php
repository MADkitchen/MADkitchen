<?php

/*
 * Copyright (C) 2022 Giovanni Cascione <ing.cascione@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace MADkitchen\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Collection of methods to handle MADkitchen database tables.
 *
 * @since       0.1
 *
 * @package     Database
 * @subpackage  TablesHandler
 */
class TablesHandler {

    public static function create_std_lookup_table($tag, $desc, $external_keys = []) {
        $retval = [];
        $external_keys_schema = [];
        $external_keys_columns = [];

        $tag = \MADkitchen\Helpers\Common::sanitize_key($tag);

        if (is_array($external_keys)) {
            foreach ($external_keys as $item) {
                if (!empty($item['tag'])) {

                    $additional_keys = [];
                    if (!empty($item['type'])) {
                        $schema_type = $item['type'];
                        $column_type = $item['type'];
                        if (!empty($item['length'])) {
                            $schema_type = "{$item['type']}({$item['length']})";
                            $additional_keys['length'] = $item['length'];
                        }
                    } else {
                        $column_type = 'bigint';
                        $schema_type = 'bigint(20)';
                        $additional_keys['length'] = '20';
                    }
                    if (!empty($item['description'])) {

                        $additional_keys['description'] = $item['description'];
                    }

                    //relation is to specified tag by default, unless it is expressly indicated as false
                    if (isset($item['relation']) && $item['relation'] === false) {
                        unset($item['relation']);
                    } else {
                        $additional_keys['relation'] = $item['relation'] ?? $item['tag'];
                    }

                    $external_keys_schema[] = "{$item['tag']} {$schema_type} NOT NULL";
                    $external_keys_columns[$item['tag']] = array_merge([
                        'type' => $column_type,
                        'unsigned' => true,
                            ], $additional_keys);
                }
            }
        }

        if (!empty($tag)) {
            $retval = [
                $tag => [
                    'lookup_table' => true,
                    'schema' => join(",\n", array_merge(
                                    [
                                        "id  bigint(20) NOT NULL AUTO_INCREMENT",
                                        "PRIMARY KEY (id)",
                                        "$tag tinytext NOT NULL",
                                        "{$tag}_name tinytext NOT NULL",
                                        "{$tag}_desc text NOT NULL"
                                    ],
                                    $external_keys_schema))
                    ,
                    'columns' => array_merge(
                            [
                                'id' => [
                                    'type' => 'bigint',
                                    'length' => '20',
                                    'unsigned' => true,
                                    'extra' => 'auto_increment',
                                    'primary' => true,
                                    'sortable' => true,
                                ],
                                $tag => [
                                    'description' => $desc,
                                    'type' => 'tinytext',
                                    'unsigned' => true,
                                    'searchable' => true,
                                    'sortable' => true,
                                ],
                                "{$tag}_name" => [
                                    'description' => "Name of $tag item",
                                    'type' => 'tinytext',
                                    'unsigned' => true,
                                    'searchable' => true,
                                    'sortable' => true,
                                ],
                                "{$tag}_desc" => [
                                    'description' => "Description of $tag item",
                                    'type' => 'text',
                                    'unsigned' => true,
                                    'searchable' => true,
                                    'sortable' => true,
                                ],
                            ],
                            $external_keys_columns),
                ],
            ];
        }


        return $retval;
    }

    /*
     * Returns the base table name.
     *
     * @since 0.1
     *
     * @param string $module_name The module name for which the base table name is built.
     * @return string The base table name.
     */

    public static function get_default_table_name($module_name) {
        return MK_TABLES_PREFIX . strtolower($module_name) . '_table';
    }

    /*
     * Returns the source table name of a given column.
     *
     * @since 0.1
     *
     * @param string $class The target MADkitchen module class
     * @param string $column The target column name
     * @return string|null The value of the setting
     */

    public static function get_source_table($class, $column) {
        $tables_data = self::get_tables_data($class);
        foreach ($tables_data as $table_key => $table_data) {
            if (ColumnsHandler::is_column_existing($class, $table_key, $column) && !ColumnsHandler::is_column_reference($class, $table_key, $column)) {
                return $table_key;
            }
        }
    }

    /*
     * Returns the array of table names where specified column is included as external reference.
     *
     * @since 0.2
     *
     * @param string $class The target MADkitchen module class
     * @param string $column The target column name
     * @return array The array of table names where specified column is included as reference
     */

    public static function get_referred_tables($class, $column) {
        $tables_data = self::get_tables_data($class);
        $res = [];
        foreach ($tables_data as $table_key => $table_data) {
            if (ColumnsHandler::is_column_existing($class, $table_key, $column) && ColumnsHandler::is_column_reference($class, $table_key, $column)) {
                $res[] = $table_key;
            }
        }
        return $res;
    }

    public static function get_tables_data($class, $table = null) {

        $retval = \MADkitchen\Modules\Handler::$active_modules[$class]['class']->table_data ?? null;
        if (!empty($table) && is_scalar($table)) {
            $retval = $retval[$table] ?? null;
        }

        return $retval;
    }

    /*
     * Tests if specified table is included in specified module class.
     *
     * @since 0.2
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param string $column The target column name
     * @return bool True if specified column is included in specified table as an external reference, false otherwise
     */

    public static function is_table_existing($class, $table) {
        return !empty(self::get_tables_data($class, $table));
    }

}
