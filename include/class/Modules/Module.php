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
    protected $use_common_styles = false;
    protected $use_common_scripts = false;
    protected $styles = [];
    protected $scripts = [];

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

    public function query($table = '0', $query = array(), $force_direct_query = false) {
        $class = "\\" . MK_MODULES_NAMESPACE . "$this->name\\$table\\Query";

        $retval = new $class();
        if ($force_direct_query === true) {
            $retval->query_direct($query);
        } else {
            $retval->query($query);
        }
        return $retval;
    }

    protected function table($table = '0') {
        $class = "\\" . MK_MODULES_NAMESPACE . "$this->name\\$table\\Table";
        return new $class();
    }

    public function load_module() {
        //TODO: check tests
        //Database support
        if ($this->table_data && is_array($this->table_data)) {
            foreach ($this->table_data as $key => $table) {
                if (isset($table['columns']) && isset($table['schema'])) { //TODO: check if table_data['schema'] is actually a minimum requirement
                    //TODO: check if incorporate here
                    $table_name = \MADkitchen\Database\TablesHandler::get_default_table_name($this->name) . "_$key";
                    $namespace = $this->namespace . "\\$key";

                    //Schema
                    array_walk($table['columns'], function (&$value, $key) {
                        $value['name'] = $key;
                    });
                    $columns_var_exp = var_export($table['columns'], true);
                    eval("namespace $namespace;class Schema extends \BerlinDB\Database\Schema{protected \$columns=$columns_var_exp;}");

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
        $init_includes = array(
                ["source" => "functions.php", "functions" => ['include_source']],
                ["source" => "ajax_functions.php", "functions" => ['include_source', 'add_ajax_hooks']],
        );
        foreach ($init_includes as $item) {
            $target = MK_MODULES_PATH . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR . $item['source'];
            if (file_exists($target)) {
                foreach ($item['functions'] as $function) {
                    [$this, $function]($target);
                }
            }
        }

        //Scripts and styles
        if ($this->use_common_scripts) {
            array_unshift($this->scripts, ['common_frontend_js', plugin_dir_url(__FILE__) . '/assets/js/frontend.js', array('jquery')]);
        }

        if ($this->use_common_styles) {
            array_unshift($this->styles, ['common_frontend_css', plugin_dir_url(__FILE__) . '/assets/css/frontend.css']);
        }

        if ($this->styles) {
            add_action('wp_enqueue_scripts', function () {
                $this->handle_styles_scripts($this->styles);
            });
        }

        if ($this->scripts) {
            add_action('wp_enqueue_scripts', function () {
                $this->handle_styles_scripts($this->scripts);
            });
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

    private function handle_styles_scripts($args) {
        foreach ($args as $item) {
            if (isset($item[1]) && substr($item[1], -4) === ".css") {
                if (!wp_style_is('w3css', 'registered')) {
                    wp_register_style(...$item);
                }
                if (!wp_style_is('w3css', 'enqueued')) {
                    wp_enqueue_style($item[0]);
                }
            } else if (isset($item[1]) && substr($item[1], -4) === ".js") {
                if (!wp_script_is('w3css', 'registered')) {
                    wp_register_script(...$item);
                }
                if (!wp_script_is('w3css', 'enqueued')) {
                    wp_enqueue_script($item[0]);
                }
            }
        }
    }

    private function include_source($source) {
        include_once $source;
    }

    private function add_ajax_hooks($source) {
        $source_content = file_get_contents($source);
        preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $source_content, $matches);
        foreach ($matches[1] as $match) {
            add_action('wp_ajax_' . $match, $match);
            add_action('wp_ajax_nopriv_' . $match, $match);
        }
    }
}
