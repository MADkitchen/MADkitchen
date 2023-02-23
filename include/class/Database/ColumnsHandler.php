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
 * @subpackage  Handler
 */
class ColumnsHandler {

    /**
     * List of accepted aggregating functions in a query.
     *
     * @since 0.2
     *
     * @var array
     */
    private static $query_aggregate_func_list = [
        //COUNT(*) already covered in BerlinDB
        'sum',
        'min',
        'max',
        'average',
        'group_concat',
        'first',
        'last',
    ];

    public static function get_query_aggregate_func_list() {
        return self::$query_aggregate_func_list;
    }

    /*
     * Returns the value of a column setting from the module class definitions.
     *
     * If setting is not found in the specified table, source table is tried instead.
     *
     * @since 0.1
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param string $column_name The name of the target column for the setting search.
     * @param string $setting The setting name searched (keys of 'columns' element from \MADkitchen\Modules\Module 'table_data' property).
     * @return string|null The value of the setting
     */

    public static function get_table_column_setting($class, $table, $column_name, $setting) {
        $table_data = TablesHandler::get_tables_data($class, $table);

        if (!empty($table_data['columns'][$column_name][$setting]))
            return $table_data['columns'][$column_name][$setting];

        return TablesHandler::get_tables_data($class, TablesHandler::get_source_table($class, $column_name))['columns'][$column_name][$setting] ?? null;
    }

    /*
     * Tests if specified column is included in specified module class\table as an external reference.
     *
     * @since 0.2
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param string $column The target column name
     * @return bool True if specified column is included in specified table as an external reference, false otherwise
     */

    public static function is_column_reference($class, $table, $column) {
        return !empty(TablesHandler::get_tables_data($class, $table)['columns'][$column]['relation']);
    }

    /*
     * Tests if specified column is existing in specified module class\table.
     *
     * @since 0.2
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param string $column The target column name
     * @return bool True if specified column is included in specified table, false otherwise
     */

    public static function is_column_existing($class, $table, $column) {
        return !empty(TablesHandler::get_tables_data($class, $table)['columns'][$column]);
    }

    /*
     * Tests if specified column is the result of a query aggregating function.
     *
     * @see Handler::query_aggregate_func_list
     * @since 0.2
     *
     * @param string $column The target column name
     * @return bool True if specified column is the result of a query aggregating function, false otherwise
     */

    public static function is_column_aggregated($column) {
        return !self::filter_aggregated_column_name($column) === $column;
    }

    /*
     * Possibly remove query aggregating functions prefix from specified column name
     *
     * Returns specified column unchanged if no prefix is found.
     *
     * @since 0.2
     *
     * @param string $column The target column name
     * @return string Filtered column name
     */

    public static function filter_aggregated_column_name($column) {
        $aggregate_prefixes = array_map(fn($x) => $x . '_', array_merge(self::get_query_aggregate_func_list(), ['count']));
        return str_replace($aggregate_prefixes, '', $column);
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

    public static function get_primary_key_column_name($class, $table) {
        $retval = null;
        $table_data = TablesHandler::get_tables_data($class, $table);

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
