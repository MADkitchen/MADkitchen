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
 * @package     Database
 * @subpackage  Item
 * @since       0.1
 */
class ColumnsResolver {

    //TODO: consider manual getters for performance
    use \MADkitchen\Helpers\MagicGet;

    /**
     * The target MADkitchen module class
     *
     * @var string
     * @since 0.1
     */
    protected $class;

    /**
     * The source table name.
     *
     * It is the original lookup table name, if applicable.
     *
     * @var string
     * @since 0.1
     */
    protected $table;
    private $primary = '';
    private $resolved = [];
    private $aggregated = [];
    private $external = [];
    private $external_source = [];
    private $referral = [];
    private $referral_source = [];

    /*
     * Creates ColumnsResolver object on successful initialization.
     *
     * @param string $class The target MADkitchen module class
     * @param string $table The target table name
     * @param array $column_names Limit the columns resolved to $column_names array instead of all table columns
     * @return \MADkitchen\Database\ColumnsResolver|null Created ColumnsResolver object or null on failure
     */
    function __construct(string $class, string $table, array $column_names = []) {

        $this->class = (!empty($class) && !empty(\MADkitchen\Modules\Handler::$active_modules[$class]['class'])) ? $class : null;
        $this->table = !empty($this->class) && Handler::is_table_existing($class, $table) ? $table : null;

        if (empty($this->table))
            return;

        $this->primary = \MADkitchen\Database\Handler::get_primary_key_column_name($this->class, $this->table);

        if (empty($column_names))
            $column_names = array_keys(\MADkitchen\Database\Handler::get_tables_data($this->class, $this->table)['columns']);

        // Separate local column names from column referring to external tables
        foreach ($column_names as $column_name) {
            $filtered_column_name = Handler::filter_aggregated_column_name($column_name);
            if (Handler::is_column_existing($this->class, $this->table, $filtered_column_name)) {
                $column_source_table = Handler::is_column_reference($this->class, $this->table, $filtered_column_name) ? Handler::get_source_table($this->class, $filtered_column_name) : $this->table;

                if ($column_source_table === $this->table) {
                    if (Handler::is_column_aggregated($column_name)) {
                        $this->aggregated[] = $column_name;
                    } else if ($this->primary !== $column_name) {
                        $this->resolved[] = $column_name;
                    }
                } else {
                    if (!Handler::is_column_aggregated($column_name)) {
                        $this->referral[] = $column_name;
                        $this->referral_source[$column_name] = $column_source_table;
                    }
                }
            } else {
                if (!Handler::is_column_aggregated($column_name)) {
                    $column_source_table = Handler::get_source_table($this->class, $filtered_column_name);
                    if ($column_source_table) {
                        $this->external[] = $column_name;
                        $this->external_source[$column_name] = $column_source_table;
                    }
                }
            }
        }
    }

    public function get_local() {
        return array_merge($this->aggregated, $this->resolved, $this->referral);
    }

    public function get_local_no_aggregated() {
        return array_merge($this->resolved, $this->referral);
    }

    public function is_valid() {
        return !empty($this->primary);
    }

}
