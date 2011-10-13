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
    class Poi_Camping extends Poi_Base {
        const VERSION = '0.3';
        const POST_TYPE = 'pgm_camping';
        const PREFIX = 'pgm_camping_';

        /**
         * Constructor
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function __construct() {
            parent::__construct(self::POST_TYPE, self::PREFIX);

            // Register actions, filters and shortcodes
            add_action('admin_init', array($this, 'addMetaBoxes'));
            add_action('admin_enqueue_scripts', array($this, 'loadMapAdminResources'), 11);
            
            // Gestion de listado de administracion
            add_filter('parse_query', array($this, 'sortAdminView'));
            add_filter('manage_edit-' . self::POST_TYPE . '_columns', array($this, 'add_new_pgm_columns'));
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'manage_pgm_columns'));
            add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', array($this, 'sortable_pgm_columns'));
            add_filter('request', array($this, 'column_orderby'));

            // Add filtros de taxonomy al listado de administracion
            add_action('restrict_manage_posts', array($this, 'todo_restrict_manage_posts'));
            add_filter('parse_query', array($this, 'todo_convert_restrict'));
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
            add_meta_box(self::PREFIX . 'poi-address', 'Dirección', array($this, 'markupAddressFields'), self::POST_TYPE, 'normal', 'high');
            add_meta_box(self::PREFIX . 'attachments', 'Fotos', array($this, 'markupAttachments'), self::POST_TYPE, 'normal', 'high');
            add_meta_box(self::PREFIX . 'videos', 'Videos', array($this, 'markupVideos'), self::POST_TYPE, 'normal', 'high');
        }

        public function loadMapAdminResources(){
            global $post;
            //Mapa de GoogleMaps
            if(is_admin()){
                wp_register_script('googleMapsAPI', 'http' . ( is_ssl() ? 's' : '' ) . '://maps.google.com/maps/api/js?sensor=false', false, false, true);
                wp_enqueue_script('googleMapsAPI');
                wp_register_script('pgmAdmin', plugins_url('poi-google-maps/javascript/pgm_gmaps_admin.js'), array('googleMapsAPI', 'jquery'), self::VERSION, true);
                wp_enqueue_script('pgmAdmin');
                
                //Establecemos los parametros del mapa
                if(get_post_meta($post->ID, self::PREFIX . 'latitude', true)){
                    $map_latitude = get_post_meta($post->ID, self::PREFIX . 'latitude', true);
                    $map_longitude = get_post_meta($post->ID, self::PREFIX . 'longitude', true);
                    $marker_latitude = get_post_meta($post->ID, self::PREFIX . 'latitude', true);
                    $marker_longitude = get_post_meta($post->ID, self::PREFIX . 'longitude', true);
                } else {
                    $map_latitude = "40.4093135509089";
                    $map_longitude = "-3.636474429687496";
                    $marker_latitude = "40.4093135509089";
                    $marker_longitude = "-3.636474429687496";
                }
                
                //Establecemos las opciones del mapa de GoogleMaps para la administración
                $options = array(
                    'mapWidth' => '350',
                    'mapHeight' => '200',
                    'latitude' => $map_latitude,
                    'longitude' => $map_longitude,
                    'zoom' => '8'
                );

                //Establecemos el punto si ya ha sido seleccionado
                $placemarks = array();
                $placemarks[] = array(
                    'title' => '',
                    'latitude' => $marker_latitude,
                    'longitude' => $marker_longitude,
                    'draggable' => true,
                    'icon' => plugins_url('poi-google-maps/images/default-marker.png')
                );

                $pgmAdminData = sprintf("pgmAdminData.options = %s;\r\npgmAdminData.markers = %s", json_encode($options), json_encode($placemarks));
                wp_localize_script('pgmAdmin', 'pgmAdminData', array('l10n_print_after' => $pgmAdminData));
               
            }
        }
        
        /**
         * Outputs the markup for the address fields
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function markupAddressFields() {
            global $post;

            $address = get_post_meta($post->ID, self::PREFIX . 'address', true);
            $city = get_post_meta($post->ID, self::PREFIX . 'city', true);
            $postalcode = get_post_meta($post->ID, self::PREFIX . 'postalcode', true);
            $telephone = get_post_meta($post->ID, self::PREFIX . 'telephone', true);
            $fax = get_post_meta($post->ID, self::PREFIX . 'fax', true);
            $email = get_post_meta($post->ID, self::PREFIX . 'email', true);
            $website = get_post_meta($post->ID, self::PREFIX . 'website', true);
            
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

            //We get an array with every videos attached ( single=false )
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
         * Saves values of the the custom post type's extra fields
         * @param
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function saveCustomFields($postID, $post) {
            //Init Errors
            $errors = false;

            if ($post && $post->post_type == self::POST_TYPE && current_user_can('edit_posts') && $_GET['action'] != 'trash' && $_GET['action'] != 'untrash') {
                if (( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft')
                    return;

                // Save address
                update_post_meta($post->ID, self::PREFIX . 'address', $_POST[self::PREFIX . 'address']);
                update_post_meta($post->ID, self::PREFIX . 'city', $_POST[self::PREFIX . 'city']);
                update_post_meta($post->ID, self::PREFIX . 'postalcode', $_POST[self::PREFIX . 'postalcode']);
                update_post_meta($post->ID, self::PREFIX . 'telephone', $_POST[self::PREFIX . 'telephone']);
                update_post_meta($post->ID, self::PREFIX . 'fax', $_POST[self::PREFIX . 'fax']);
                update_post_meta($post->ID, self::PREFIX . 'email', $_POST[self::PREFIX . 'email']);
                update_post_meta($post->ID, self::PREFIX . 'website', $_POST[self::PREFIX . 'website']);

                update_post_meta($post->ID, self::PREFIX . 'latitude', $_POST[self::PREFIX . 'latitude']);
                update_post_meta($post->ID, self::PREFIX . 'longitude', $_POST[self::PREFIX . 'longitude']);

                // Save video
                if($_POST[self::PREFIX . 'video'] != ""){
                    if (filter_var($_POST[self::PREFIX . 'video'], FILTER_VALIDATE_URL) === FALSE) {
                        //update_post_meta( $post->ID, self::PREFIX . 'video', 0 );
                        $this->enqueueMessage('La url '. $_POST[self::PREFIX . 'video'] .' no es una URL válida', 'error');
                    } elseif (preg_match('%.*?v=([a-z0-9\-_]+).*%', $_POST[self::PREFIX . 'videos']) !== FALSE)
                        add_post_meta($post->ID, self::PREFIX . 'videos', $_POST[self::PREFIX . 'video'], false);                    
                }
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

    }

}
?>
