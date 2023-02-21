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
        return empty(\MADkitchen\Database\Handler::get_tables_data($class, $table)['lookup_table']) ? false : true;
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

        if (empty($query) || empty($table_cols = \MADkitchen\Database\Handler::get_tables_data($class, $table)['columns']))
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
                                    'groupby' => [\MADkitchen\Database\Handler::get_primary_key_column_name($class, $table)],
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
                $query = Handler::get_columns_array_from_rows($table_data, is_scalar($query['groupby']) ? [$query['groupby']] : $query['groupby'], true);
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

    //TODO: check names function/vars...
    //TODO: check synergy with get_table_column_prop_by_key
    //TODO check if it can be simplified with is_lookup_table
    /*
     * Finds all independent columns from tables other than $this_table related to $target_column in that table. Primary key columns are excluded.
     *
     * @param string $class The target MADkitchen module class
     * @param string $target_column The name of the target column for which the external master column is searched.
     * @param string $this_table The name of the table where searched target column is contained
     * @return array Array of possible external master columns from the module class
     */
    public static function find_related_external_lookup_columns($class, $target_column, $this_table) {
        $retval = [];
        $this_table_data = \MADkitchen\Database\Handler::get_tables_data($class, $this_table);
        $all_tables_data = \MADkitchen\Database\Handler::get_tables_data($class);
        unset($all_tables_data[$this_table]);

        foreach ($all_tables_data as $table_name => $table_data) {
            //Find independent columns in each table correlated to this column (independent or not)
            if (isset($table_data['columns'][$target_column]) && !isset($this_table_data['columns'][$target_column])) {
                unset($table_data['columns'][$target_column]);
                foreach ($table_data['columns'] as $column_name => $column_data) {
                    //Check for independent columns only, excluding primary key which is obvious.
                    if (!\MADkitchen\Database\Handler::is_column_external($class, $table_name, $column_name) &&
                            $column_name != \MADkitchen\Database\Handler::get_primary_key_column_name($class, $table_name))
                        $retval[] = $column_name;
                }
            }
        }

        return $retval;
    }

    public static function recursively_resolve_lookup_group($class, $data_cols, $query = [], $base_table = null) {
        $data_cols = array_values($data_cols); //TODO: check if associative arrays are really needed upstream
        $data_buffer = $data_cols;
        $data_tot = [];
        $watchdog = 0;
        do {
            $i = false;
            foreach ($data_buffer as $column_name) {
                $this_table = $base_table ?? \MADkitchen\Database\Handler::get_source_table($class, $column_name);
                $lookup_columns = array_intersect($data_cols, \MADkitchen\Database\Lookup::find_related_external_lookup_columns($class, $column_name, $this_table));
                $column_item = new MADkitchen\Database\Item($class, $column_name);
                $found = null;
                if (empty($lookup_columns)) {
                    $watchdog = 0;

                    $default_args = self::is_lookup_table($class, $this_table) ? [] : [
                        'groupby' => [$column_name],
                    ];

                    $x = \MADkitchen\Modules\Handler::$active_modules[$class]['class']->query($this_table,  array_merge($default_args, $query))->items;

                    foreach ($x as $item) {
                        if (\MADkitchen\Database\Handler::get_source_table($class, $column_name) === ($this_table)) { //will not be used eventually...
                            $column_item->set_row($item);
                        } else {
                            $column_item->set_key($item->$column_name);
                        }
                        if (!empty($column_item)) {
                            $data_tot[$column_name][] = $column_item;
                        }
                    }
                    $data_buffer = array_diff($data_buffer, [$column_name]);
                } else {
                    $i = true;
                    $lookup_column = reset($lookup_columns);
                    if (!empty($data_tot[$lookup_column])) { //first match only
                        $search_table = ts_get_table_source($lookup_column);

                        if (MADkitchen\Database\Handler::is_column_external('TimeTracker', $search_table, $column_name)) {
                            $x = ts_query_items(
                                    ['id' => array_map(fn($y) => $y->row->$column_name, //TODO generalize 'id'
                                                $data_tot[$lookup_column]),
                                    /* 'orderby' => [
                                      $a,
                                      ], */
                                    ],
                                    ts_get_table_source($column_name)
                            );
                            foreach ($x as $item) {
                                $column_item->set_row($item); //ts_get_entry_by_id($a, $item->$a);
                                if (!empty($column_item)) {
                                    $data_tot[$column_name][] = $column_item;
                                }
                            }
                        } else {
                            foreach ($data_tot[$lookup_column] as $lookup_entry) {
                                $column_item->set_row( $lookup_entry->row);
                                if (!empty($column_item)) {
                                    $data_tot[$column_name][] = $column_item;
                                }
                            }
                        }
                        $data_buffer = array_diff($data_buffer, [$column_name]);
                    }
                }
            }
            if ($watchdog++ > 3)
                break;
        } while ($i);

        return MADkitchen\Helpers\Common::ksort_by_array($data_tot, $data_cols);
    }

}
