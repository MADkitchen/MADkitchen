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

class Entry {

    protected $column;
    protected $value;
    protected $key;
    protected $table;
    protected $row;

    private function reset() {
        $this->value = null;
        $this->key = null;
        $this->row = null;
    }

    public function __get($key = '') {

        // Class method to try and call
        $method = "get_{$key}";

        // Return property if exists
        if (method_exists($this, $method)) {
            return call_user_func(array($this, $method));

            // Return get method results if exists
        } elseif (property_exists($this, $key)) {
            return $this->{$key};
        }

        // Return null if not exists
        return null;
    }

    public function __construct($class, $column) {

        $this->class = $this->set_class($class);

        $this->table = $this->get_source_table($column);

        if (empty($this->table)) {
            return null;
        } else {
            $this->column = $column;
        }
    }

    public function get_source_table($column) {
        $tables_data = \MADkitchen\Database\Handler::get_tables_data($this->class);
        foreach ($tables_data as $table_key => $table_data) {

            if (!empty($tables_data[$table_key]['columns'][$column]) && empty($tables_data[$table_key]['columns'][$column]['relation'])) {
                return $table_key;
            }
        }
    }

    public function is_source_table($table_key) {
        return (get_source_table($table_key) === $table_key);
    }

    private function set_class($class) {
        if (!empty($class) && !empty(\MADkitchen\Modules\Handler::$active_modules[$class]['class'])) {
            return $class;
        }
    }

    public function set_key($key) {
        $key = (int) $key;
        $this->reset();
        if ($key > 0) {
            $this->key = $this->set_column_value_by_key($key);
        }
        return $this->row;
    }

    public function set_value($value) {
        $this->reset();
        //TODO: add mysql sanitization and test!
        $this->value = $this->set_key_by_column_value($value);
        return $this->row;
    }

    public function set_row(\Berlindb\Database\Row $row) {
        $this->reset();

        $this->row = $row;
        $this->key = $row->id; //TODO: generalize id key
        $this->value = $row->{$this->column};
    }

    private function set_column_value_by_key($key) {

        $primary_key = \MADkitchen\Database\Handler::get_primary_key($this->class, $this->table);

        if (!empty($primary_key)) {
            $this->row = $this->retrieve_row($primary_key, $key) ?? null;
            $this->value = $this->row->{$this->column} ?? null;
        }

        return empty($this->value) ? null : $key;
    }

    private function set_key_by_column_value($value) {

        $this->row = $this->retrieve_row($this->column, $value) ?? null;
        $this->key = $this->row->{\MADkitchen\Database\Handler::get_primary_key($this->class, $this->table)} ?? null;

        return empty($this->key) ? null : $value;
    }

    private function retrieve_row($column, $value) {
        $retval = null;
        if (empty($retval = \MADkitchen\Database\Handler::get_row_from_lookup_table($this->class, $this->table, $value, $column))) {
            $retval = reset(\MADkitchen\Modules\Handler::$active_modules[$this->class]['class']->query($this->table, [
                        $column => $value, //get entire row instead?
                            //'groupby' => [$column_target],
                            ]
                    )->items);
        }
        return $retval;
    }

}
