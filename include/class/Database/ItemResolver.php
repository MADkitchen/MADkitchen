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
class ItemResolver {

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
     * The target column name
     *
     * @var string
     * @since 0.1
     */
    protected $column;

    /**
     * The table element value from target column corresponding to target row (or target primary id key)
     *
     * The value is possibly resolved in the original lookup table.
     *
     * @var string
     * @since 0.1
     */
    protected $value;

    /**
     * The key in the primary id column of the column table
     *
     * It is the key in the original lookup table, if applicable.
     *
     * @var string
     * @since 0.1
     */
    protected $primary_key;

    /**
     * The source table name.
     *
     * It is the original lookup table name, if applicable.
     *
     * @var string
     * @since 0.1
     */
    protected $source_table;

    /**
     * The source table corresponding row object
     *
     * @var \Berlindb\Database\Row
     * @since 0.1
     */
    protected $row;

    /**
     * Wether the ItemResolver object is valid or not
     *
     * @var bool
     * @since 0.1
     */
    private $is_valid = false;

    /**
     * Resets current value / coordinates
     *
     * @param void
     * @return void
     */
    private function reset() {
        $this->value = null;
        $this->primary_key = null;
        $this->row = null;
        //$this->ctx_row = null;
    }

    /**
     * Class constructor
     *
     * If data_ctx and table_ctx are provided, object is initialized with set_item_in_context method.
     *
     * @param string $class The target MADkitchen module class
     * @param string $column The target column name
     * @return void
     */
    public function __construct(string $class, string $column, mixed $data_ctx = null, string $table_ctx = '') {

        $this->class = (!empty($class) && !empty(\MADkitchen\Modules\Handler::$active_modules[$class]['class'])) ? $class : null;
        $this->source_table = !empty($this->class) ? Handler::get_source_table($class, Handler::filter_aggregated_column_name($column)) : null;
        $this->column = !empty($this->source_table) ? $column : null;

        if (!empty($this->column) && Handler::get_primary_key_column_name($this->class, $this->source_table) !== $this->column) {
            $this->is_valid = true;
        }

        if (!is_null($data_ctx) && $table_ctx !== '') {
            $this->set_item_in_context($data_ctx, $table_ctx);
        }
    }

    /**
     * Starts item resolution by primary key in source table.
     *
     * Sets a target primary id column key as coordinate and stores the corresponding row and table value of current target column.
     *
     * @param string $primary_key The value in the primary id column of the table
     * @return \Berlindb\Database\Row The row corresponding to the primary id key set
     */
    public function set_key($primary_key) {
        if (!$this->is_valid)
            return;
        $primary_key = (int) $primary_key;
        $this->reset();
        if ($primary_key > 0 && !empty($primary_key_column_name = \MADkitchen\Database\Handler::get_primary_key_column_name($this->class, $this->source_table))) {
            $this->row = reset(\MADkitchen\Modules\Handler::$active_modules[$this->class]['class']->query($this->source_table, [
                        $primary_key_column_name => $primary_key,
                            ]
                    )->items);
            $this->value = $this->row[$this->column] ?? null;
            $this->primary_key = empty($this->value) ? null : $primary_key;
        }
        return $this->row;
    }

    /**
     * Starts item resolution by value in source table.
     *
     * Sets a target table value from current target column as coordinate and stores the FIRST row and primary id column key found.
     *
     * @param string $value The table element from target column
     * @return \Berlindb\Database\Row The row corresponding to the primary id key set
     */
    public function set_value($value) {
        if (!$this->is_valid)
            return;
        $this->reset();
        $this->row = reset(\MADkitchen\Modules\Handler::$active_modules[$this->class]['class']->query($this->source_table, [
                    $this->column => $value,
                        ]
                )->items);
        $this->primary_key = $this->row->{\MADkitchen\Database\Handler::get_primary_key_column_name($this->class, $this->source_table)} ?? null;
        $this->value = empty($this->primary_key) ? null : $value;
        return $this->row;
    }

    /**
     * Sets the row for inverse resolution.
     *
     * Sets a target table row and stores the corresponding current target column value and primary id column key.
     *
     * @param \Berlindb\Database\Row $row The target row
     * @return void
     */
    public function force_row(mixed $row) {
        if (!$this->is_valid)
            return;
        $this->reset();
        $this->row = $row;
        $this->primary_key = $this->row[Handler::get_primary_key_column_name($this->class, $this->source_table)] ?? null;
        $this->value = $this->row[$this->column] ?? null;
    }

    /**
     * Resolves the item in a specific context table
     *
     * Sets an arbitrary context table and starts resolution by providing the target column value or the Row object in that table.
     * The value or the Row object are interpreted based on module target column settings for the specified context table.
     *
     * @param string $data_ctx Column primary key value or Row from $table_ctx to resolve item
     * @param string $table_ctx Context table for resolution with $data_ctx
     * @return \Berlindb\Database\Row The row corresponding to the primary id key set
     */
    public function set_item_in_context(mixed $data_ctx, string $table_ctx) {
        if (!$this->is_valid)
            return;
        if (!empty(Handler::get_tables_data($this->class)[$table_ctx]) && Handler::is_column_existing($this->class, $table_ctx, Handler::filter_aggregated_column_name($this->column))) {
            $this->reset();
            if ($this->source_table !== $table_ctx) {
                if (is_scalar($data_ctx)) {
                    $this->set_key($data_ctx);
                } else {
                    $this->set_key($data_ctx[$this->column]);
                }
            } else {
                if (is_scalar($data_ctx)) {
                    $this->set_value($data_ctx);
                } else {
                    $this->force_row($data_ctx);
                }
            }
            return $this->row;
        }
    }

    public function is_resolved() {
        return (!empty($this->value) && $this->is_valid);
    }

    public function is_valid() {
        return $this->is_valid;
    }

    public function __toString(): string {
        return $this->column . $this->value . $this->primary_key;
    }

}
