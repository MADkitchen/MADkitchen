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

class Module {

    protected $dependencies = array();
    protected $autoload = false;
    protected $name = '';
    protected $namespace = '';
    protected $table_data = array();
    protected $pages_data = array();

    public function __isset($key = '') {

        // Class method to try and call
        $method = "get_{$key}";

        // Return property if exists
        if (method_exists($this, $method)) {
            return true;

            // Return get method results if exists
        } elseif (property_exists($this, $key)) {
            return true;
        }

        // Return false if not exists
        return false;
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

    public function __construct() {

        $this->namespace = get_class($this);
        //TODO: check if incorporate here
        $this->name = \MADkitchen\Modules\Handler::get_module_name($this->namespace);

        $this->init_module();
    }

    public function query($table = '0', $query = array()) {
        $class = "\\" . MK_MODULES_NAMESPACE . "$this->name_$table\\Query";

        return new $class($query);
    }

    protected function table($table = '0') {
        $class = "\\" . MK_MODULES_NAMESPACE . "$this->name_$table\\Table";
        return new $class();
    }

    public function load_module() {
        //TODO: check tests
        //Database support
        if ($this->table_data && is_array($this->table_data)) {
            foreach ($this->table_data as $key => $table) {
                if (isset($table['columns']) && isset($table['schema'])) { //TODO: check if table_data['schema'] is actually a minimum requirement
                    //TODO: check if incorporate here
                    $table_name = \MADkitchen\Modules\Handler::get_default_table_name($this->name) . "_$key";
                    $namespace = $this->namespace . "_$key";

                    //Schema
                    $columns = var_export($table['columns'], true);
                    eval("namespace $namespace;class Schema extends \BerlinDB\Database\Schema{protected \$columns=$columns;}");

                    //Table
                    $schema = addslashes($table['schema']);
                    eval("namespace $namespace;class Table extends \MADkitchen\Database\Table{public \$name=\"$table_name\";protected \$schema=\"$schema\";}");

                    //Query
                    $table_schema = addslashes($namespace) . '\\Schema';
                    eval("namespace $namespace;class Query extends \MADkitchen\Database\Query{protected \$table_name=\"$table_name\";protected \$table_schema=\"$table_schema\";}");

                    //Autoload Table class
                    $class = "$namespace\\Table";
                    new $class;
                }
            }
        }

        //Custom pages support
        if ($this->pages_data) {
            $pages = var_export($this->pages_data, true);
            eval("namespace $this->namespace;class Page extends \MADkitchen\Frontend\Page{protected \$module_name=\"$this->name\";protected \$pages=$pages;}");

            //Autoload Page class
            $class = "$this->namespace\\Page";
            new $class;
        }

        //Default includes
        $init_includes = array("functions.php");
        foreach ($init_includes as $item) {
            $target = MK_MODULES_PATH . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR . $item;
            if (file_exists($target)) {
                include_once $target;
            }
        }

        \MADkitchen\Modules\Handler::$active_modules[$this->name]['is_loaded'] = true;
    }

    protected function init_module() {

        if ($this->dependencies) {
            foreach ($this->dependencies as $module) {
                $item = \MADkitchen\Modules\Handler::maybe_load_module($module);
                if ($item && !$item['is_loaded']) {
                    $item['class']->load_module();
                }
            }
        }

        if ($this->autoload) {
            $this->load_module();
        }
    }

}
