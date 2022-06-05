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

namespace MADkitchen\Modules;

class Handler {

    public static $active_modules = array();

    public static function load_modules() {
        foreach (scandir(MK_MODULES_PATH) as $key => $value) {
            if (!in_array($value, array(".", "..")) &&
                    is_dir(MK_MODULES_PATH . "/" . $value)) {
                self::maybe_load_module($value);
            }
        }
    }

    public static function maybe_load_module($value) {
        $retval = null;
        if ((!isset(self::$active_modules[$value]) || !self::$active_modules[$value])) {
            $class = MK_MODULES_NAMESPACE . $value;

            if (class_exists($class, true)) { //CHECK autoloader impact
                self::$active_modules[$value]['is_loaded'] = false;
                self::$active_modules[$value]['class'] = new $class;
                $retval = self::$active_modules[$value];
            }
        } else {
            $retval = self::$active_modules[$value];
        }
        return $retval;
    }

    public static function get_default_table_name($module_name) {
        return MK_TABLES_PREFIX . strtolower($module_name) . '_table';
    }

    public static function get_module_name($class_name) {
        if (!empty($class_name)) {
            $trimmed_classname = str_replace(MK_MODULES_NAMESPACE, '', $class_name);
            if ($trimmed_classname != $class_name) { //test namespace as per template
                $buffer = explode('\\', $trimmed_classname);
                return array_shift($buffer);
            }
        }
    }

    public static function get_module_path($module_name) {
        if (!empty($module_name)) {
            return MK_MODULES_PATH . DIRECTORY_SEPARATOR . $module_name;
        }
    }

    public static function resolve_internal_relation($class, $table, $id, $column, $target = null) {
        $retval = $id;
        $target = is_null($target) ?: $column;

        $table_data = self::get_table($class, $table);
        $primary_key = get_primary_key($class, $table);

        if (isset($table_data['columns'][$column]['relation']) && key_exists($primary_key, $table_data)) {
            $new_table = $table_data['columns'][$column]['relation'];
            $found = self::$active_modules[$class]['class']->query($new_table, array(
                        $primary_key => $id
                            )
                    )->items;
            if (isset($found[0][$target])) {
                $retval = self::resolve_internal_relation($class, $new_table, $found[0][$target], $target);
            }
        }

        return $retval;
    }

    public static function get_primary_key($class, $table) {
        $retval = null;
        $table_data = self::get_table($class, $table);

        foreach ($table_data as $key => $column) {

            if (get_table_column_prop_by_key($class, $table, $key, 'primary') === true) {
                $retval = $key;
            }
        }

        return $retval;
    }

    public static function get_table($class, $table) {
        $retval = [];
        if (isset(self::$active_modules[$class]['class']->table_data[$table])) {
            $retval = self::$active_modules[$class]['class']->table_data[$table];
        }
        return $retval;
    }

    public static function get_table_column_prop_array_by_key($class, $table, $keys_in, $prop = 'name', $key_out_type = 'name', $get_val_from = array()) {
        $retval = [];
        foreach ($keys_in as $key) {
            $prop_out = self::get_table_column_prop_by_key($class, $table, $key, $prop);
            $key_out = self::get_table_column_prop_by_key($class, $table, $key, $key_out_type);

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

    public static function get_table_column_prop_by_key($class, $table, $key, $prop) {
        $retval = null;
        $table_data = self::get_table($class, $table);
        if (isset($table_data['columns'][$key]) && isset($table_data['columns'][$key][$prop])) {
            $retval = $table_data['columns'][$key][$prop];
        }

        return $retval;
    }

}
