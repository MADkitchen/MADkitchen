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
defined( 'ABSPATH' ) || exit;

// Register a closure to autoload BerlinDB.
spl_autoload_register(

	/**
	 * Closure of the autoloader.
	 *
	 * @param string $class_name The fully-qualified class name.
	 * @return void
	 */
	static function ( $class_name = '' ) {

		// Project namespace & length.
		$project_namespace = 'MADkit\\';
		$length            = strlen( $project_namespace );

		// Bail if class is not in this namespace.
		if ( 0 !== strncmp( $project_namespace, $class_name, $length ) ) {
			return;
		}

		// Setup file parts.
		$format = MK_PATH. DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR,MK_CLASS_SUBPATH) . DIRECTORY_SEPARATOR . '%s.php';
		$name   = str_replace( '\\', DIRECTORY_SEPARATOR, str_replace( $project_namespace, '', $class_name ) );

		// Parse class and namespace to file.
		$file   = sprintf( $format, $name );

		// Bail if file does not exist.
		if ( ! is_file( $file ) ) {
			return;
		}

		// Require the file.
		require $file;
	}
);
