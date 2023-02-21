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
class Item {

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
     * The table element from target column corresponding to target row (or target primary id key)
     *
     * @var string
     * @since 0.1
     */
    protected $value;

    /**
     * The value in the primary id column of the table
     *
     * @var string
     * @since 0.1
     */
    protected $primary_key;

    /**
     * The table name
     *
     * @var string
     * @since 0.1
     */
    protected $source_table;

    /**
     * The table row object
     *
     * @var \Berlindb\Database\Row
     * @since 0.1
     */
    protected $row;

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
    }

    /**
     * Class constructor
     *
     * @param string $class The target MADkitchen module class
     * @param string $column The target column name
     * @return void
     */
    public function __construct($class, $column) {

        $this->class = (!empty($class) && !empty(\MADkitchen\Modules\Handler::$active_modules[$class]['class'])) ? $class : null;
        $this->source_table = $this->class ? \MADkitchen\Database\Handler::get_source_table($class, $column) : null;
        $this->column = $this->source_table ? $column : null;
    }

    /**
     * Sets a target primary id column key as coordinate and stores the corresponding row and table value of current target column.
     *
     * @param string $primary_key The value in the primary id column of the table
     * @return \Berlindb\Database\Row The row corresponding to the primary id key set
     */
    public function set_key($primary_key) {
        $primary_key = (int) $primary_key;
        $this->reset();
        if ($primary_key > 0 && !empty($primary_key_column_name = \MADkitchen\Database\Handler::get_primary_key_column_name($this->class, $this->source_table))) {
            $this->row = reset(\MADkitchen\Modules\Handler::$active_modules[$this->class]['class']->query($this->source_table, [
                        $primary_key_column_name => $primary_key,
                            ]
                    )->items);
            $this->value = $this->row->{$this->column} ?? null;
            $this->primary_key = empty($this->value) ? null : $primary_key;
        }
        return $this->row;
    }

    /**
     * Sets a target table value from current target column as coordinate and stores the first row and the first primary id column key found.
     *
     * @param string $value The table element from target column
     * @return \Berlindb\Database\Row The row corresponding to the primary id key set
     */
    public function set_value($value) {
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
     * Sets a target table row and stores the corresponding table value from current target column and primary id column key.
     *
     * @param \Berlindb\Database\Row $row The target row
     * @return void
     */
    public function set_row(\Berlindb\Database\Row $row) {
        $this->reset();
        $this->row = $row;
        $this->primary_key = $row->{\MADkitchen\Database\Handler::get_primary_key_column_name($this->class, $this->source_table)};
        $this->value = $row->{$this->column};
    }

}
