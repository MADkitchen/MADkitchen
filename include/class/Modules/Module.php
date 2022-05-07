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

namespace MADkit\Modules;

class Module {

    protected $module_dependencies = array();
    protected $name = '';
    protected $table_name = '';
    protected $namespace = '';
    protected $table_data = array();
    protected $pages_data = array();

    public function __construct($query = array()) {

        $this->namespace = get_class($this);
        //TODO: check if incorporate here
        $this->name = \MADkit\Modules\Handler::get_module_name($this->namespace);
        //TODO: check if incorporate here
        $this->table_name = \MADkit\Modules\Handler::get_default_table_name($this->name);

        $this->init_module();
    }

    public function query($query) {
        $class = "\\" . MK_MODULES_NAMESPACE . "$this->name\\Query";
        return new $class($this->name, $this->namespace, $query);
    }

    protected function table() {
        $class = "\\" . MK_MODULES_NAMESPACE . "$this->name\\Table";
        return new $class($this->name);
    }

    protected function init_module() {
        //TODO: check tests
        //Table
        if (isset($this->table_data['schema'])) {
            $schema = addslashes($this->table_data['schema']);
            eval("namespace $this->namespace;class Table extends \MADkit\Database\Table{public \$name=\"$this->table_name\";protected \$schema=\"$schema\";}");
            $class="$this->namespace\\Table";
            new $class;
        }

        //Schema
        if (isset($this->table_data['columns'])) {
            $columns = var_export($this->table_data['columns'], true);
            eval("namespace $this->namespace;class Schema extends \BerlinDB\Database\Schema{protected \$columns=$columns;}");
        }

        //Query
        $table_schema = $this->namespace . '\\Schema';
        eval("namespace $this->namespace;class Query extends \MADkit\Database\Query{protected \$table_name=\"$this->table_name\";protected \$table_schema=\"$table_schema\";}");

        //Pages
        if ($this->pages_data) {
            $pages = var_export($this->pages_data, true);
            eval("namespace $this->namespace;class Page extends \MADkit\Frontend\Page{protected \$module_name=\"$this->name\";protected \$pages=$pages;}");
            $class="$this->namespace\\Page";
            new $class;
        }

        //Other includes
        $init_includes = array("functions.php");
        foreach ($init_includes as $item) {
            $target = MK_MODULES_PATH . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR . $item;
            if (file_exists($target)) {
                include_once $target;
            }
        }
    }

}
