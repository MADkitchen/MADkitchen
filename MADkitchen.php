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

/**
 * Plugin Name:       MADkitchen
 * Plugin URI:        https://github.com/MADkitchen/MADkitchen/
 * Description:       A Modular App Development kit for WordPress
 * Version:           0.1.0
 * Author:            MADkitchen
 * Author URI:        https://github.com/MADkitchen/
 * Text Domain:       MADkitchen
 * Domain Path:       /languages
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define Constants
 *
 * @since 0.1.0
 */
define("MK_PATH", __DIR__);
define("MK_NAME", 'MADkitchen');
define("MK_VERSION", '0.1.0');

define("MK_CLASS_SUBPATH", array('include', 'class'));
define("MK_MODULES_PATH", MK_PATH . DIRECTORY_SEPARATOR . 'modules');
define("MK_MODULES_CLASS_SUBPATH", array('class'));
define("MK_MODULES_NAMESPACE", 'MADkitchen\\Module\\');
define("MK_TABLES_PREFIX", 'mk_');

define("MK_OPTIONS_PREFIX", '_mk_');
define('MK_OPTIONS_PAGE_BASENAME', 'MADkitchen-settings');
define("MK_OPTIONS_NAME", 'mk_options');

/**
 * Define Globals
 *
 * @since 0.1.0
 */
$mk_plugin_url = plugin_dir_url(__FILE__);

/**
 * Define Autoloaders
 *
 * @since 0.1.0
 */
require_once( MK_PATH . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, MK_CLASS_SUBPATH) . '/Modules/autoloader.php' ); //TODO: merge in main autoloader
require_once( MK_PATH . '/autoloader.php' );

/**
 * Load installed modules
 *
 * @since 0.1.0
 */
$modules = MADkitchen\Modules\Handler::load_modules();

