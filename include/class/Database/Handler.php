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

class Handler {

    public static function get_default_table_name($module_name) {
        return MK_TABLES_PREFIX . strtolower($module_name) . '_table';
    }

    public static function get_primary_key($class, $table) {
        $retval = null;
        $table_data = self::get_table($class, $table);

        foreach ($table_data['columns'] as $key => $column) {
            if (!empty($column['primary'])) {
                $retval = $key;
                break;
            }
        }

        return $retval;
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
                $found = \MADkitchen\Modules\Handler::$active_modules[$class]['class']->query($table_target, [
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

    public static function get_table($class, $table) {
        $retval = [];
        if (isset(\MADkitchen\Modules\Handler::$active_modules[$class]['class']->table_data[$table])) {
            $retval = \MADkitchen\Modules\Handler::$active_modules[$class]['class']->table_data[$table];
        }
        return $retval;
    }

    public static function get_table_column_prop_by_key($class, $table, $key, $prop) {
        $retval = null;
        $table_data = self::get_table($class, $table);
        if (isset($table_data['columns'][$key]) && isset($table_data['columns'][$key][$prop])) {
            if (empty($table_data['columns'][$key]['relation'])) {
                $retval = $table_data['columns'][$key][$prop];
            } else { //If it's external key search in that table
                $retval = self::get_table_column_prop_by_key($class, $table_data['columns'][$key]['relation'], $key, $prop);
            }
        } else { //If nothing is found try the other tables from that class to find original column
            foreach (\MADkitchen\Modules\Handler::$active_modules[$class]['class']->table_data as $key_tab => $value_tab) {
                if (key_exists($key, $value_tab['columns']) &&
                        isset($value_tab['columns'][$key_tab][$prop]) &&
                        (!isset($value_tab['columns'][$key_tab]['relation']) || !$value_tab['columns'][$key_tab]['relation'])) {
                    $retval = $value_tab['columns'][$key_tab][$prop];
                    break;
                }
            }
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

    public static function get_std_lookup_table($tag, $desc, $external_keys = []) {
        $retval = [];
        $external_keys_schema = [];
        $external_keys_columns = [];

        $tag = \MADkitchen\Helpers\Common::sanitize_key($tag);

        if (is_array($external_keys)) {
            foreach ($external_keys as $item) {
                if (!empty($item['tag'])) {
                    $item['relation'] = $item['relation'] ?? $item['tag'];
                    $external_keys_schema[] = "{$item['tag']} bigint(20) NOT NULL";
                    $external_keys_columns = [
                        $item['tag'] => [
                            'name' => $item['tag'],
                            'type' => 'bigint',
                            'length' => '20',
                            'unsigned' => true,
                            'relation' => $item['relation'],
                        ],
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
                                'id' => [
                                    'name' => 'id',
                                    'type' => 'bigint',
                                    'length' => '20',
                                    'unsigned' => true,
                                    'extra' => 'auto_increment',
                                    'primary' => true,
                                    'sortable' => true,
                                ],
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
