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

    public static function resolve_internal_relation($class, $table_source, $column_source, $id_source, $column_target = null) {
        $retval = $id_source;
        $column_target = is_null($column_target) ? $column_source : $column_target;

        $table_source_data = self::get_table($class, $table_source);

        $table_target = null;
        if (isset($table_source_data['columns'][$column_source]['relation'])) {
            $table_target = $table_source_data['columns'][$column_source]['relation'];
            //$table_target_data = self::get_table($class, $table_target);
            $primary_key = self::get_primary_key($class, $table_target);

            if (!is_null($primary_key)) {
                $found = self::$active_modules[$class]['class']->query($table_target, [
                            $primary_key => $id_source,
                            'groupby' => [$column_target],
                                ]
                        )->items;
                if (isset($found[0]->$column_target)) {
                    $retval = self::resolve_internal_relation($class, $table_target, $column_target, $found[0]->$column_target);
                }
            }
        }




        return $retval;
    }

    public static function get_primary_key($class, $table) {
        $retval = null;
        $table_data = self::get_table($class, $table);

        foreach ($table_data['columns'] as $key => $column) {

            if (self::get_table_column_prop_by_key($class, $table, $key, 'primary') === true) {
                $retval = $key;
                break;
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

    public static function sanitize_key($key) {
        if (is_scalar($key)) {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
        } else {
            return null;
        }
    }

    public static function get_std_lookup_table($tag, $desc, $external_keys = []) {
        $retval = [];
        $external_keys_schema = [];
        $external_keys_columns = [];

        $tag = self::sanitize_key($tag);

        if (is_array($external_keys)) {
            foreach ($external_keys as $item) {
                if (!empty($item['tag'])) {
                    $item['relation'] = $item['relation'] ?? $item['tag'];
                    $external_keys_schema[] = "{$item['tag']} bigint(20) NOT NULL";
                    $external_keys_columns[
                            $item['tag']] = [
                        'type' => 'bigint',
                        'length' => '20',
                        'unsigned' => true,
                        'relation' => $item['relation'],
                    ];
                }
            }
        }

        if (!empty($tag)) {
            $retval = [
                $tag => [
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
                                $tag => [
                                    'name' => $tag,
                                    'description' => $desc,
                                    'type' => 'tinytext',
                                    'unsigned' => true,
                                    'searchable' => true,
                                    'sortable' => true,
                                ],
                                "{$tag}_name" => [
                                    'name' => "{$tag}_name",
                                    'description' => "Name of $tag item",
                                    'type' => 'tinytext',
                                    'unsigned' => true,
                                    'searchable' => true,
                                    'sortable' => true,
                                ],
                                //job_tag
                                "{$tag}_desc" => [
                                    'name' => "{$tag}_desc",
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

}
