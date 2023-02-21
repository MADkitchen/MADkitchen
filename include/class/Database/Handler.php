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
 * @package     Database
 * @subpackage  Handler
 * @since       0.1
 */
class Handler {

    public static $query_aggregate_func_list = [
        //COUNT(*) already covered in BerlinDB
        'sum',
        'min',
        'max',
        'average',
        'group_concat',
        'first',
        'last',
    ];

    public static function get_default_table_name($module_name) {
        return MK_TABLES_PREFIX . strtolower($module_name) . '_table';
    }

    /*
     * Gets the value of a column setting from module class definitions.
     *
     * If setting is not found in requested table, source table is tried instead.
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param string $column_name The name of the target column for the setting search.
     * @param string $setting The setting name searched (keys of 'columns' element from \MADkitchen\Modules\Module 'table_data' property).
     * @return string|null The value of the setting
     */

    public static function get_table_column_setting($class, $table, $column_name, $setting) {
        $table_data = self::get_tables_data($class, $table);

        if (!empty($table_data['columns'][$column_name][$setting]))
            return $table_data['columns'][$column_name][$setting];

        return self::get_tables_data($class, self::get_source_table($class, $column_name))['columns'][$column_name][$setting] ?? null;
    }

    public static function get_source_table($class, $column) {
        $tables_data = self::get_tables_data($class);
        foreach ($tables_data as $table_key => $table_data) {
            if (self::is_column_existing($class, $table_key, $column) && !self::is_column_reference($class, $table_key, $column)) {
                return $table_key;
            }
        }
    }

    public static function get_referred_tables($class, $column) {
        $tables_data = self::get_tables_data($class);
        $res = [];
        foreach ($tables_data as $table_key => $table_data) {
            if (self::is_column_existing($class, $table_key, $column) && self::is_column_reference($class, $table_key, $column)) {
                $res[] = $table_key;
            }
        }
        return $res;
    }

    public static function is_column_reference($class, $table, $column) {
        return !empty(self::get_tables_data($class, $table)['columns'][$column]['relation']);
    }

    public static function is_column_existing($class, $table, $column) {
        return !empty(self::get_tables_data($class, $table)['columns'][$column]);
    }

    public static function is_table_existing($class, $table) {
        return !empty(self::get_tables_data($class, $table));
    }

    public static function filter_aggregated_column_name($aggregate_column) {
        $aggregate_prefixes = array_map(fn($x) => $x . '_', array_merge(\MADkitchen\Database\Handler::$query_aggregate_func_list, ['count']));
        return str_replace($aggregate_prefixes, '', $aggregate_column);

        //return key_exists($original_column, self::get_tables_data($class, $table)['columns']) && $aggregate_column !== $original_column ? $original_column : false;
    }

    public static function is_column_aggregated($column) {
        return !self::filter_aggregated_column_name($column) === $column;

        //return key_exists($original_column, self::get_tables_data($class, $table)['columns']) && $aggregate_column !== $original_column ? $original_column : false;
    }

    public static function remove_aggregate_columns(array $columns) {
        $retval = [];
        foreach ($columns as $column) {
            if (self::is_column_aggregated($column))
                $retval[] = $column;
        }
        return $retval;
    }

    //TODO: simplify and separate external array filter function
    public static function get_table_column_settings_array($class, $table, $keys_in, $prop = 'name', $key_out_type = 'name', $get_val_from = array()) {
        $retval = [];
        foreach ($keys_in as $key) {
            //TODO: check if recursion is actually needed or not
            if (is_array($key)) {
                self::get_table_column_settings_array($class, $table, $key, $prop, $key_out_type, $get_val_from);
            }
            $prop_out = self::get_table_column_setting($class, $table, $key, $prop) ?: 'name'; //name workaround!
            $key_out = self::get_table_column_setting($class, $table, $key, $key_out_type) ?: 'name'; //name workaround!

            if ($prop_out === '' || $key_out === '') {
                continue;
            }

            if ($get_val_from) {
                if (isset($get_val_from[$prop_out])) {
                    $retval[$key_out] = $get_val_from[$prop_out];
                }
            } else if ($key_out_type === false) {
                $retval[] = $prop_out;
            } else {
                $retval[$key_out] = $prop_out;
            }
        }

        return $retval;
    }

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

    public static function get_tables_data($class, $table = null) {

        $retval = \MADkitchen\Modules\Handler::$active_modules[$class]['class']->table_data ?? null;
        if (!empty($table) && is_scalar($table)) {
            $retval = $retval[$table] ?? null;
        }

        return $retval;
    }

    public static function get_primary_key_column_name($class, $table) {
        $retval = null;
        $table_data = \MADkitchen\Database\Handler::get_tables_data($class, $table);

        if (isset($table_data['columns'])) {
            foreach ($table_data['columns'] as $key => $column) {
                if (!empty($column['primary'])) {
                    $retval = $key;
                    break;
                }
            }
        }
        return $retval;
    }

    //review first
    public static function get_columns_array_from_rows($rows, $columns_filter = null, $unique = false) {
        $retval = [];

        if (empty($rows))
            return $retval;

        $row_keys = array_keys(reset($rows));
        $filter = empty($columns_filter) ? $row_keys : array_intersect($columns_filter, $row_keys);

        foreach ($rows as $key => $row) {
            foreach ($filter as $key => $value) {
                $retval[$value][] = $row[$value];
            }
        }

        if ($unique) {
            $retval = array_map(fn($x) => array_unique($x), $retval);
        }
        return empty($columns_filter) ? $retval : \MADkitchen\Helpers\Common::ksort_by_array($retval, $columns_filter);
    }

    public static function get_columns_array_primary_keys_from_rows($rows, $columns_filter = null, $unique = false) {
        $retval = self::get_columns_array_from_rows($rows, $columns_filter, $unique);

        //TODO:improve and add tests?
        $test = gettype(reset(reset($retval)));

        if ($test === 'object') {
            $retval = array_map(fn($x) => array_map(fn($y) => $y->primary_key, $x), $retval); //TODO generalize as primary key is not available for aggregates
        }

        return empty($columns_filter) ? $retval : \MADkitchen\Helpers\Common::ksort_by_array($retval, $columns_filter);
    }

    public static function get_columns_array_values_from_rows($rows, $columns_filter = null, $unique = false) {
        $retval = self::get_columns_array_from_rows($rows, $columns_filter, $unique);

        //TODO:improve and add tests?
        $test = gettype(reset(reset($retval)));

        if ($test === 'object') {
            $retval = array_map(fn($x) => array_map(fn($y) => $y->value, $x), $retval); //TODO generalize as primary key is not available for aggregates
        }

        return empty($columns_filter) ? $retval : \MADkitchen\Helpers\Common::ksort_by_array($retval, $columns_filter);
    }

}
