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

// Exit if accessed directly.
defined('ABSPATH') || exit;

spl_autoload_register(
        /**
         * Closure of the autoloader.
         *
         * @param string $class_name The fully-qualified class name.
         * @return void
         */
        static function ($class_name = '') {

            // Project namespace & length.
            $project_namespace = MK_MODULES_NAMESPACE;
            $length = strlen($project_namespace);

            // Bail if class is not in this namespace.
            if (0 !== strncmp($project_namespace, $class_name, $length)) {
                return;
            }

            // Setup file parts.
            // Parse class and namespace to file.
            $trimmed_class_name = str_replace($project_namespace, '', $class_name);

            $array_class_name = explode('\\', $trimmed_class_name);
            if (count($array_class_name)>1) {
                $class = array_pop($array_class_name);
                $name = join(DIRECTORY_SEPARATOR, $array_class_name);
                $format = MK_MODULES_PATH . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, MK_MODULES_CLASS_SUBPATH) . DIRECTORY_SEPARATOR . '%s.php';
            } else {
                //first level
                $format = MK_MODULES_PATH . DIRECTORY_SEPARATOR . '%s.php';
                $class = $trimmed_class_name.DIRECTORY_SEPARATOR . $trimmed_class_name;
            }

            $file = sprintf($format, $class);

            // Bail if file does not exist.
            if (!is_file($file)) {
                return;
            }

            // Require the file.
            require $file;
        }
);
