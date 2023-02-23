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

/*
 * Collection of methods to handle MADkitchen lookup tables anf lookup operation.
 *
 * @package     Database
 * @subpackage  Lookup
 * @since       0.1
 */

class Lookup {
    /*
     * Buffer to store in memory all lookup tables for quick access.
     *
     * Structure:
     * [ module_class_name => [
     *      lookup_table_name => [
     *          BerlinDB_rows, ... ],
     *           ... ],
     *      ... ],
     * ... ]
     *
     * @var array
     * @see is_lookup_table()
     * @since 0.1
     */

    public static $lookup_tables = array();

    /*
     * Checks if target table is defined as a lookup table
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @return bool True if table is defined as a lookup table, false otherwise
     */

    public static function is_lookup_table($class, $table) {
        return empty(TablesHandler::get_tables_data($class, $table)['lookup_table']) ? false : true;
    }

    /*
     * Checks if query is simple one which can be addressed to lookup tables.
     *
     * Simple query contains only column search key (to resolve referred items) or one groupby key (to retrieve items list).
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param string $query The BerlinDB Query class query array
     * @return bool True if is simple query.
     */

    public static function is_simple_query($class, $table, $query) {

        $filtered_keys = [
            'number',
            'order'
        ];

        $query = array_diff_key($query, array_flip($filtered_keys));

        if (empty($query) || empty($table_cols = TablesHandler::get_tables_data($class, $table)['columns']))
            return false;

        //simple item list query
        if (key_exists('groupby', $query) && count($query) === 1)
            return true;

        //single item query
        if (count($item = array_intersect_assoc(array_keys($table_cols), array_keys($query))) === count($query))
            return true;

        return false;
    }

    /*
     * Tries to retrieve target table from lookup table buffer.
     * If lookup table is not in the buffer yet, it is retrieved from database and added.
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @return array|null Array of \Berlindb\Database\Row elements from the target table
     */

    public static function maybe_get_lookup_table($class, $table) {
        $retval = null;
        if (self::is_lookup_table($class, $table)) {
            if (!isset(self::$lookup_tables[$class][$table])) {
                self::$lookup_tables[$class][$table] = \MADkitchen\Modules\Handler::$active_modules[$class]['class']->query($table,
                                [
                                    'groupby' => [\MADkitchen\Database\ColumnsHandler::get_primary_key_column_name($class, $table)],
                                ],
                                true
                        )->items;
            }
            $retval = self::$lookup_tables[$class][$table];
        }
        return $retval;
    }

    /*
     * Tries to address simple lookup queries to lookup table buffer instead of db and return matching rows.
     *
     * Condition for the rows to match the query array is (column1_item1 OR column1_item2 OR ...) AND (column2_item1 OR column2_item2 OR ...) AND ...
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param string $query The BerlinDB Query class query array
     * @return array|null Array of \Berlindb\Database\Row elements from the target table
     */

    public static function simple_lookup_query($class, $table, $query) {
        $retval = false;

        if (self::is_simple_query($class, $table, $query) && $table_data = self::maybe_get_lookup_table($class, $table)) {
            if (key_exists('groupby', $query)) {
                // Change groupby list to columns search arrays
                $query = ColumnsHandler::get_columns_array_from_rows($table_data, is_scalar($query['groupby']) ? [$query['groupby']] : $query['groupby'], true);
            }
            foreach ($table_data as $row) {
                foreach ($query as $column_name => $column_data) {
                    $include_row = false;
                    $column_data = is_array($column_data) ? $column_data : [$column_data];
                    foreach ($column_data as $column_item) {
                        $include_row = $row[$column_name] == $column_item ?: $include_row;
                    }
                }
                if (!empty($include_row))
                    $retval[] = $row;
            }
        }
        return $retval;
    }

    /*
     * Creates ItemResolver object on successful initialization.
     *
     * @param string $class The target MADkitchen module class
     * @param string $column The target column name
     * @param string $data_ctx Column primary key value or Row from $table_ctx to resolve item
     * @param string $table_ctx Context table for resolution with $data_ctx
     * @return \MADkitchen\Database\ItemResolver|null Created ItemResolver object or null on failure
     */

    public static function build_ItemResolver(string $class, string $column, mixed $data_ctx = null, string $table_ctx = '') {
        $retval = new ItemResolver($class, $column, $data_ctx, $table_ctx);
        if ($retval->is_valid() === false || (!empty($data_ctx) && $retval->is_resolved() === false)) {
            return null;
        } else {
            return $retval;
        }
    }

    /*
     * Mimic a groupby query by a specific column on a given ItemResolver row set
     *
     * @param array $rows The ItemResolver row set
     * @param string $groupby_column The column used to group rows
     * @param array $sum_up_columns Columns to be summed up during the grouping.
     * @return \MADkitchen\Database\ColumnsResolver|null Created ColumnsResolver object or null on failure
     */

    public static function groupby_items_rows_by_column(array $rows, string $groupby_column, array $sum_up_columns = []) {
        $retval = [];
        $unique_column_values = ColumnsHandler::get_columns_array_values_from_rows($rows, [$groupby_column], true);

        if (empty($unique_column_values))
            return $retval;

        foreach ($unique_column_values[$groupby_column] as $key => $value) {
            $found_first_row = false;
            foreach ($rows as $row) {
                if ($row[$groupby_column]->value === $value) {
                    if (!$found_first_row) {
                        $retval[$key] = array_map(fn($y) => $y->value, $row);
                        $found_first_row = true;
                    } else {
                        if (empty($sum_up_columns)) {
                            break;
                        } else {
                            foreach ($sum_up_columns as $sum_up_column) {
                                $retval[$key][$sum_up_column] = $retval[$key][$sum_up_column] + $row[$sum_up_column]->value ?? $retval[$key][$sum_up_column];
                            }
                        }
                    }
                }
            }
        }

        return $retval;
    }

}
