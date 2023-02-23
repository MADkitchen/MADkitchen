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
 * Class to handle single items from a specific MADkitchen table column.
 *
 * @since       0.2
 *
 * @package     Database
 * @subpackage  Item
 */
class ColumnsResolver {

    //TODO: consider manual getters for performance
    use \MADkitchen\Helpers\MagicGet;

    /**
     * The target MADkitchen module class
     *
     * @since 0.2
     *
     * @var string
     */
    protected $class;

    /**
     * The source table name.
     *
     * @since 0.2
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key column name.
     *
     * @since 0.2
     *
     * @var string
     */
    private $primary = '';

    /**
     * The names array of column defined in current table.
     *
     * Value of this columns are actually reported in current table.
     *
     * @since 0.2
     *
     * @var array
     */
    private $resolved = [];

    /**
     * The names array of columns which are results of aggregation functions.
     *
     * @see ColumnsHandler::query_aggregate_func_list
     * @since 0.2
     *
     * @var array
     */
    private $aggregated = [];

    /**
     * The names array of columns which are not included in current table.
     *
     * @since 0.2
     *
     * @var array
     */
    private $external = [];

    /**
     * The names array of source tables of the stored external columns.
     *
     * Items indexes are consistent with external columns array ones.
     *
     * @since 0.2
     *
     * @var array
     */
    private $external_source = [];

    /**
     * The names array of columns which are included as a reference only and defined in an external table.
     *
     * Value in this columns correspond to primary key index in the source table.
     *
     * @since 0.2
     *
     * @var array
     */
    private $referral = [];

    /**
     * The names array of source tables of the stored referral columns.
     *
     * Items indexes are consistent with referral columns array ones.
     *
     * @since 0.2
     *
     * @var array
     */
    private $referral_source = [];

    /*
     * ColumnsResolver class constructor.
     *
     * Parses column of a given class/column and divide them in groups depending on data origin.
     *
     * @since 0.2
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param array $column_names Use specified column names array instead of table columns per table definition
     */

    function __construct(string $class, string $table, array $column_names = []) {

        $this->class = (!empty($class) && !empty(\MADkitchen\Modules\Handler::$active_modules[$class]['class'])) ? $class : null;
        $this->table = !empty($this->class) && TablesHandler::is_table_existing($class, $table) ? $table : null;

        if (empty($this->table))
            return;

        $this->primary = \MADkitchen\Database\ColumnsHandler::get_primary_key_column_name($this->class, $this->table);

        if (empty($column_names))
            $column_names = array_keys(\MADkitchen\Database\TablesHandler::get_tables_data($this->class, $this->table)['columns']);

        // Separate local column names from column referring to external tables
        foreach ($column_names as $column_name) {
            $filtered_column_name = ColumnsHandler::filter_aggregated_column_name($column_name);
            if (ColumnsHandler::is_column_existing($this->class, $this->table, $filtered_column_name)) {
                $column_source_table = ColumnsHandler::is_column_reference($this->class, $this->table, $filtered_column_name) ? TablesHandler::get_source_table($this->class, $filtered_column_name) : $this->table;

                if ($column_source_table === $this->table) {
                    if (ColumnsHandler::is_column_aggregated($column_name)) {
                        $this->aggregated[] = $column_name;
                    } else if ($this->primary !== $column_name) {
                        $this->resolved[] = $column_name;
                    }
                } else {
                    if (!ColumnsHandler::is_column_aggregated($column_name)) {
                        $this->referral[] = $column_name;
                        $this->referral_source[$column_name] = $column_source_table;
                    }
                }
            } else {
                if (!ColumnsHandler::is_column_aggregated($column_name)) {
                    $column_source_table = TablesHandler::get_source_table($this->class, $filtered_column_name);
                    if ($column_source_table) {
                        $this->external[] = $column_name;
                        $this->external_source[$column_name] = $column_source_table;
                    }
                }
            }
        }
    }

    /*
     * Returns all column names which are not external, including results of agregated functions
     *
     * @since 0.2
     *
     * @return array Local columns names array
     */

    public function get_local() {
        return array_merge($this->aggregated, $this->resolved, $this->referral);
    }

    /*
     * Returns all column names which are not external, excluding results of agregated functions
     *
     * @since 0.2
     *
     * @return array Local columns names array, excluding aggregated ones
     */

    public function get_local_no_aggregated() {
        return array_merge($this->resolved, $this->referral);
    }

}
