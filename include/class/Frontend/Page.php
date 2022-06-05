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

namespace MADkitchen\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Page {

    /**
     * Allow pings?
     * @var string
     */
    protected $ping_status = 'open';
    protected $pages = array();
    protected $module_name = '';

    /**
     * Class constructor
     */
    public function __construct() {

        add_filter('the_posts', array($this, 'inject_page'), 10, 2);
        add_action('wp_head', array($this, 'add_page_scripts'));
        //TODO: check if load without any check if template exists
        $this->add_page_functions();
    }

    private function create_page($page_item) {

        /**
         * Create a fake post.
         */
        $post = new \stdClass;

        /**
         * The author ID for the post.  Usually 1 is the sys admin.  Your
         * plugin can find out the real author ID without any trouble.
         */
        $post->post_author = 1;

        /**
         * The safe name for the post.  This is the post slug.
         */
        $post->post_name = $page_item['slug'];

        /**
         * Not sure if this is even important. But gonna fill it up anyway.
         */
        $post->guid = get_bloginfo('wpurl') . '/' . $page_item['slug'];

        /**
         * The title of the page.
         */
        $post->post_title = $page_item['title'];

        /**
         * This is the content of the post.  This is where the output of
         * your plugin should go.  Just store the output from all your
         * plugin function calls, and put the output into this var.
         */
        $post->post_content = $this->add_page_content($page_item);

        /**
         * Fake post ID to prevent WP from trying to show comments for
         * a post that doesn't really exist.
         */
        //TODO: find a cleaner way...
        $post->ID = 1000000000000;

        /**
         * Static means a page, not a post.
         */
        $post->post_status = 'static';

        /**
         * Turning off comments for the post.
         */
        $post->comment_status = 'closed';

        /**
         * Let people ping the post?  Probably doesn't matter since
         * comments are turned off, so not sure if WP would even
         * show the pings.
         */
        $post->ping_status = $this->ping_status;

        $post->comment_count = 0;

        /**
         * You can pretty much fill these up with anything you want.  The
         * current date is fine.  It's a fake post right?  Maybe the date
         * the plugin was activated?
         */
        $post->post_date = current_time('mysql');
        $post->post_date_gmt = current_time('mysql', 1);

        return($post);
    }

    public function inject_page($posts, $query) {
        /**
         * Check if the requested page matches our target
         */
        foreach ($this->pages as $item) {
            //TODO: check if test can be more specific for $query post_type
            //TODO: check if $wp test can be done with $query only
            if (key_exists('pagename', $query->query) && $query->query['pagename'] == $item['slug'] && 0 !== strncmp('wp_template', $query->query_vars['post_type'], 11)) {

                //Add the fake post
                $posts = NULL;
                $posts[] = $this->create_page($item);

                /**
                 * Trick wp_query into thinking this is a page (necessary for wp_title() at least)
                 * Not sure if it's cheating or not to modify global variables in a filter
                 * but it appears to work and the codex doesn't directly say not to.
                 */
                $query->is_page = true;
                //Not sure if this one is necessary but might as well set it like a true page
                $query->is_singular = true;
                $query->is_home = false;
                $query->is_archive = false;
                $query->is_category = false;
                //Longer permalink structures may not match the fake post slug and cause a 404 error so we catch the error here
                unset($query->query["error"]);
                $query->query_vars["error"] = "";
                $query->is_404 = false;
                break;
            }
        }
        return $posts;
    }

    private function add_page_content($page_item) {

        $template_file = join(DIRECTORY_SEPARATOR, array(\MADkitchen\Modules\Handler::get_module_path($this->module_name),
            'frontend',
            'templates',
            $this->sanitize_slug($page_item['slug']) . '.php'
                )
        );
        if (file_exists($template_file)) {
            ob_start();
            include($template_file);
            $retval = ob_get_contents();
            ob_end_clean();
        } else {
            $retval = __('Content not found', 'MADkitchen');
        }

        return $retval;
    }

    public function add_page_scripts() {

        global $wp_query;

        foreach ($this->pages as $item) {
            if (isset($wp_query->query['pagename']) && $item['slug'] == $wp_query->query['pagename']) {
                $template_file = join(DIRECTORY_SEPARATOR, array(\MADkitchen\Modules\Handler::get_module_path($this->module_name),
                    'frontend',
                    'inline_scripts',
                    $this->sanitize_slug($item['slug']) . '.php'
                        )
                );
                if (file_exists($template_file)) {
                    include($template_file);
                }
            }
        }
    }

    private function add_page_functions() {
        foreach ($this->pages as $page_item) {
            $template_file = join(DIRECTORY_SEPARATOR, array(\MADkitchen\Modules\Handler::get_module_path($this->module_name),
                'frontend',
                'functions',
                $this->sanitize_slug($page_item['slug']) . '.php'
                    )
            );

            if (file_exists($template_file)) {
                include_once($template_file);
            }
        }
    }

    private function sanitize_slug($slug) {
        //TODO: double check if this sanitization is sufficient
        return str_replace('/', '_', $slug);
    }

}
