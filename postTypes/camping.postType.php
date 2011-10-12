<?php

if ($_SERVER['SCRIPT_FILENAME'] == __FILE__)
    die("Access denied.");

if (!class_exists('Poi_Camping')) {

    /**
     * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
     * Requires PHP5+ because of various OOP features, json_encode(), pass by reference, etc
     * Requires Wordpress 3.0 because of custom post type support
     *
     * @package PoiGoogleMaps
     * @author Diego Montoto <dmontoto@gmail.com>
     * @link 
     */
    class Poi_Camping {
        const POST_TYPE = 'pgm_camping';
        const PREFIX = 'pgm_camping_';

        /**
         * Constructor
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function __construct() {
            // Register actions, filters and shortcodes
            add_action('init', array($this, 'createPostType'));
            add_action('save_post', array($this, 'saveCustomFields'));
            add_action('admin_init', array($this, 'addMetaBoxes'));
            add_action('after_setup_theme', array($this, 'addFeaturedImageSupport'), 11);
            add_action('shutdown', array($this, 'shutdown'));

            // Gestion de listado de administracion
            add_filter('parse_query', array($this, 'sortAdminView'));
            add_filter('manage_edit-' . self::POST_TYPE . '_columns', array($this, 'add_new_pgm_columns'));
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'manage_pgm_columns'));
            add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', array($this, 'sortable_pgm_columns'));
            add_filter('request', array($this, 'column_orderby'));

            // Add filtros de taxonomy al listado de administracion
            add_action('restrict_manage_posts', array($this, 'todo_restrict_manage_posts'));
            add_filter('parse_query', array($this, 'todo_convert_restrict'));

            register_activation_hook(dirname(__FILE__) . '/poi-google-maps.php', array($this, 'networkActivate'));
            register_activation_hook(dirname(__FILE__) . '/poi-google-maps.php', array($this, 'my_rewrite_flush'));
        }

        /**
         * Handles extra activation tasks for MultiSite installations
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function networkActivate() {
            global $wpdb;

            if (function_exists('is_multisite') && is_multisite()) {
                // Enable image uploads so the 'Set Featured Image' meta box will be available
                $mediaButtons = get_site_option('mu_media_buttons');

                if (!array_key_exists('image', $mediaButtons) || !$mediaButtons['image']) {
                    $mediaButtons['image'] = 1;
                    update_site_option('mu_media_buttons', $mediaButtons);
                }

                // Activate the plugin across the network if requested
                if (array_key_exists('networkwide', $_GET) && ( $_GET['networkwide'] == 1)) {
                    $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");

                    foreach ($blogs as $b) {
                        switch_to_blog($b);
                        $this->singleActivate();
                    }

                    restore_current_blog();
                }
                else
                    $this->singleActivate();
            }
            else
                $this->singleActivate();
        }

        /**
         * Prepares a single blog to use the plugin
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        protected function singleActivate() {
            // Upgrade 1.0 placemark data
            $posts = get_posts(array('numberposts' => -1, 'post_type' => self::POST_TYPE));
            if ($posts) {
                foreach ($posts as $p) {
                    $address = get_post_meta($p->ID, self::PREFIX . 'address', true);
                    $latitude = get_post_meta($p->ID, self::PREFIX . 'latitude', true);
                    $longitude = get_post_meta($p->ID, self::PREFIX . 'longitude', true);

                    if (empty($address) && !empty($latitude) && !empty($longitude)) {
                        $address = $this->reverseGeocode($latitude, $longitude);
                        if ($address)
                            update_post_meta($p->ID, self::PREFIX . 'address', $address);
                    }
                }
            }
        }

        /**
         * Runs activation code on a new WPMS site when it's created
         * @author Diego Montoto <dmontoto@gmail.com>
         * @param int $blogID
         */
        public function activateNewSite($blogID) {
            switch_to_blog($blogID);
            $this->singleActivate();
            restore_current_blog();
        }

        /**
         * Registers the custom post type
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function createPostType() {
            if (!post_type_exists(self::POST_TYPE)) {
                $labels = array(
                    'name' => __('Campings'),
                    'singular_name' => __('Camping'),
                    'add_new' => __('Add New'),
                    'add_new_item' => __('Add New Camping'),
                    'edit' => __('Edit'),
                    'edit_item' => __('Edit Camping'),
                    'new_item' => __('New Camping'),
                    'view' => __('View Camping'),
                    'view_item' => __('View Camping'),
                    'search_items' => __('Search Campings'),
                    'not_found' => __('No Campings found'),
                    'not_found_in_trash' => __('No Campings found in Trash'),
                    'parent' => __('Parent Camping')
                );

                register_post_type(
                        self::POST_TYPE, array(
                    'labels' => $labels,
                    'singular_label' => 'Campings',
                    'public' => true,
                    'menu_position' => 10,
                    'hierarchical' => false,
                    'capability_type' => 'post',
                    'rewrite' => array('slug' => 'campings', 'with_front' => false),
                    'query_var' => true,
                    'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
                    'taxonomies' => array('region'),
                        )
                );
            }
        }

        /**
         * Adds meta boxes for the custom post type
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function addMetaBoxes() {
            add_meta_box(self::PREFIX . 'poi-address', 'Poi Address', array($this, 'markupAddressFields'), self::POST_TYPE, 'normal', 'high');
            add_meta_box(self::PREFIX . 'attachments', 'Fotos', array($this, 'markupAttachments'), self::POST_TYPE, 'normal', 'high');
            add_meta_box(self::PREFIX . 'videos', 'Videos', array($this, 'markupVideos'), self::POST_TYPE, 'normal', 'high');
        }

        /**
         * Outputs the markup for the address fields
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function markupAddressFields() {
            global $post;

            $address = get_post_meta($post->ID, self::PREFIX . 'address', true);
            $latitude = get_post_meta($post->ID, self::PREFIX . 'latitude', true);
            $longitude = get_post_meta($post->ID, self::PREFIX . 'longitude', true);

            require_once( dirname(__FILE__) . '\metaboxes\meta-address.php' );
        }

        public function markupAttachments() {
            global $post;

            $args = array(
                'post_type' => 'attachment',
                'numberposts' => null,
                'post_status' => null,
                'post_parent' => $post->ID
            );

            $attachments = get_posts($args);

            if ($attachments) {
                foreach ($attachments as $attachment) {
                    //echo apply_filters('the_title', $attachment->post_title);
                    the_attachment_link($attachment->ID, false);
                }
            }
        }

        public function markupVideos() {
            global $post;

            $youtubeRegex = '%.*\?v=([a-zA-Z0-9\-_]+).*%i';
            $vimeoRegex = '/^http:\/\/(www\.)?vimeo\.com\/(clip\:)?(\d+).*$/';

            //We get an array with every videos attached to restaurant ( single=false )
            $videos = get_post_meta($post->ID, self::PREFIX . 'videos', false);

            //Show videos' thumbnails
            $video_id = array();
            foreach ($videos as $video) {
                if (filter_var($video, FILTER_VALIDATE_URL) !== FALSE
                        &&
                        ( preg_match($youtubeRegex, $video, $results) != FALSE //We use != instead of !== because we don't want FALSE, nor 0
                        || preg_match($vimeoRegex, $video, $results) != FALSE )
                ) {

                    $videoUrl = $video;
                    if (preg_match($youtubeRegex, $video, $results) != FALSE)
                        $videoImg = 'http://img.youtube.com/vi/' . $results[1] . '/default.jpg';
                    elseif (preg_match($vimeoRegex, $video, $results) != FALSE) {
                        $vimeoId = $results[3];
                        $hash = unserialize(file_get_contents('http://vimeo.com/api/v2/video/' . $vimeoId . '.php'));
                        $videoImg = $hash[0]['thumbnail_medium'];
                    }

                    printf('<a href="%s" target="_blank"><img src="%s"></a>', $video, $videoImg);
                }
            }

            //Show new video's form
            require_once( dirname(__FILE__) . '\metaboxes\meta-videos.php' );
        }

        /**
         * Adds featured image support
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function addFeaturedImageSupport() {
            // We enabled image media buttons for MultiSite on activation, but the admin may have turned it back off
            if (is_admin() && function_exists('is_multisite') && is_multisite()) {
                $mediaButtons = get_site_option('mu_media_buttons');

                if (!array_key_exists('image', $mediaButtons) || !$mediaButtons['image']) {
                    $this->enqueueMessage(sprintf(
                                    "%s requires the Images media button setting to be enabled in order to use custom icons on markers, but it's currently turned off. If you'd like to use custom icons you can enable it on the <a href=\"%ssettings.php\">Network Settings</a> page, in the Upload Settings section.", PGM_NAME, network_admin_url()
                            ), 'error');
                }
            }

            $supportedTypes = get_theme_support('post-thumbnails');

            if ($supportedTypes === false)
                add_theme_support('post-thumbnails', array(self::POST_TYPE));
            elseif (is_array($supportedTypes)) {
                $supportedTypes[0][] = self::POST_TYPE;
                add_theme_support('post-thumbnails', $supportedTypes[0]);
            }
        }
        
        /**
         * Saves values of the the custom post type's extra fields
         * @param
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function saveCustomFields($postID) {
            global $post;
            $coordinates = false;

            if ($post && $post->post_type == self::POST_TYPE && current_user_can('edit_posts') && $_GET['action'] != 'trash' && $_GET['action'] != 'untrash') {
                if (( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft')
                    return;

                // Save address
                update_post_meta($post->ID, self::PREFIX . 'address', $_POST[self::PREFIX . 'address']);

                if ($_POST[self::PREFIX . 'address'])
                    $coordinates = $this->geocode($_POST[self::PREFIX . 'address']);

                if ($coordinates) {
                    update_post_meta($post->ID, self::PREFIX . 'latitude', $coordinates['latitude']);
                    update_post_meta($post->ID, self::PREFIX . 'longitude', $coordinates['longitude']);
                } else {
                    update_post_meta($post->ID, self::PREFIX . 'latitude', '');
                    update_post_meta($post->ID, self::PREFIX . 'longitude', '');

                    if (!empty($_POST[self::PREFIX . 'address']))
                        $this->enqueueMessage('That address couldn\'t be geocoded, please make sure that it\'s correct.', 'error');
                }

                // Save z-index
                if (filter_var($_POST[self::PREFIX . 'zIndex'], FILTER_VALIDATE_INT) === FALSE) {
                    update_post_meta($post->ID, self::PREFIX . 'zIndex', 0);
                    $this->enqueueMessage('The stacking order has to be an integer', 'error');
                }
                else
                    update_post_meta($post->ID, self::PREFIX . 'zIndex', $_POST[self::PREFIX . 'zIndex']);

                // Save video
                if (filter_var($_POST[self::PREFIX . 'video'], FILTER_VALIDATE_URL) === FALSE) {
                    //update_post_meta( $post->ID, self::PREFIX . 'video', 0 );
                    $this->enqueueMessage('El video tiene que ser una URL válida', 'error');
                } elseif (preg_match('%.*?v=([a-z0-9\-_]+).*%', $_POST[self::PREFIX . 'videos']) !== FALSE)
                    add_post_meta($post->ID, self::PREFIX . 'videos', $_POST[self::PREFIX . 'video'], false);
            }
        }

        /**
         * Sorts the posts by the title in the admin view posts screen
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        function sortAdminView($query) {
            global $pagenow;

            if (is_admin() && $pagenow == 'edit.php' && array_key_exists('post_type', $_GET) && $_GET['post_type'] == self::POST_TYPE) {
                $query->query_vars['order'] = 'ASC';
                $query->query_vars['orderby'] = 'title';
            }
        }

        /**
         * Register columns
         * @param type $columns
         * @return type 
         */
        function add_new_pgm_columns($columns) {
            $new_columns['cb'] = '<input type="checkbox" />';

            $new_columns['id'] = __('ID');
            $new_columns['title'] = _x('Camping', 'column name');
            $new_columns['region'] = __('Region');
            $new_columns['fotos'] = __('Fotos');
            $new_columns['date'] = _x('Date', 'column name');

            return $new_columns;
        }

        /**
         * Display the column content
         * @global type $wpdb
         * @param type $column_name
         * @param type $id 
         */
        function manage_pgm_columns($column_name) {
            global $post;
            switch ($column_name) {
                case 'id':
                    echo $post->ID;
                    break;

                case 'region':
                    $taxonomy = $column_name;
                    $post_type = get_post_type($post->ID);
                    $terms = get_the_terms($post->ID, $taxonomy);

                    if (!empty($terms)) {
                        foreach ($terms as $term)
                            $post_terms[] = "<a href='edit.php?post_type={$post_type}&region=" . $term->term_id . "&action=-1&mode=list&action2=-1'> " . esc_html(sanitize_term_field('name', $term->name, $term->term_id, $taxonomy, 'edit')) . "</a>";
                        echo join(', ', $post_terms);
                    }
                    else
                        echo '<i>No terms.</i>';
                    break;
                case 'fotos':
                    $args = array(
                        'post_type' => 'attachment',
                        'numberposts' => null,
                        'post_status' => null,
                        'post_parent' => $post->ID
                    );

                    $attachments = get_posts($args);

                    echo count($attachments);
                    break;
                default:
                    break;
            } // end switch
        }

        /**
         * Register columns as sortable
         * @param array $columns
         * @return string 
         */
        function sortable_pgm_columns($columns) {
            $columns['region'] = 'region';

            return $columns;
        }

        /**
         * Le decimos como ordenar
         * @param type $vars 
         */
        function column_orderby($vars) {
            if (isset($vars['orderby'])) {
                switch ($vars['orderby']) {
                    case 'region':
                        $vars = array_merge($vars, array(
                            'meta_key' => 'region',
                            'orderby' => 'meta_value_num'
                                ));
                }
            }

            return $vars;
        }

        /**
         * Add dropdowns con las taxonomies
         * @global type $typenow
         */
        function todo_restrict_manage_posts() {
            global $typenow;
            $args = array('public' => true, '_builtin' => false);
            $post_types = get_post_types($args);
            if (in_array($typenow, $post_types)) {
                $filters = get_object_taxonomies($typenow);
                foreach ($filters as $tax_slug) {
                    $tax_obj = get_taxonomy($tax_slug);
                    wp_dropdown_categories(array(
                        'show_option_all' => __('Show All ' . $tax_obj->label),
                        'taxonomy' => $tax_slug,
                        'name' => $tax_obj->name,
                        'orderby' => 'term_order',
                        'selected' => $_GET[$tax_obj->query_var],
                        'hierarchical' => $tax_obj->hierarchical,
                        'show_count' => true,
                        'hide_empty' => false
                    ));
                }
            }
        }

        /**
         * Add filters para los taxonomies
         * @global type $pagenow
         * @global type $typenow
         * @param type $query 
         */
        function todo_convert_restrict($query) {
            global $pagenow;
            global $typenow;
            if ($pagenow == 'edit.php') {
                // Aplicamos los filtros
                $filters = get_object_taxonomies($typenow);
                foreach ($filters as $tax_slug) {
                    $var = &$query->query_vars[$tax_slug];
                    if (isset($var)) {
                        $term = get_term_by('id', $var, $tax_slug);
                        $var = $term->slug;
                    }
                }
            }
        }

        /**
         * Regeneramos las rewrite rules
         */
        public function my_rewrite_flush() {
            createPostType();
            flush_rewrite_rules();
        }

        /**
         * Writes options to the database
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function shutdown() {
            if (is_admin())
                if ($this->updatedOptions)
                    update_option(self::PREFIX . 'options', $this->options);
        }

    }

}
?>
