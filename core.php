<?php
if ($_SERVER['SCRIPT_FILENAME'] == __FILE__)
    die("Access denied.");

if (!class_exists('PoiGoogleMaps')) {

    /**
     * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
     * Requires PHP5+ because of various OOP features, json_encode(), pass by reference, etc
     * Requires Wordpress 3.0 because of custom post type support
     *
     * @package PoiGoogleMaps
     * @author Diego Montoto <dmontoto@gmail.com>
     * @link 
     */
    class PoiGoogleMaps {

        // Declare variables and constants
        protected $settings, $options, $updatedOptions, $userMessageCount, $mapShortcodeCalled;
        const VERSION = '0.3';
        const PREFIX = 'pgm_';
        const POST_TYPE = 'pgm';
        const DEBUG_MODE = false;

        /**
         * Constructor
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function __construct() {
            require_once( dirname(__FILE__) . '/settings.php');

            // Initialize variables
            $defaultOptions = array('updates' => array(), 'errors' => array());
            $this->options = array_merge($defaultOptions, get_option(self::PREFIX . 'options', array()));
            $this->userMessageCount = array('updates' => count($this->options['updates']), 'errors' => count($this->options['errors']));
            $this->updatedOptions = false;
            $this->mapShortcodeCalled = false;
            $this->settings = new PGMSettings($this);

            // Register actions, filters and shortcodes
            add_action('admin_notices', array($this, 'printMessages'));
            add_action('wp', array($this, 'loadResources'), 11);
            add_action('wp_head', array($this, 'outputHead'));
            add_action('wpmu_new_blog', array($this, 'activateNewSite'));
            add_action('shutdown', array($this, 'shutdown'));

            add_shortcode('pgm-map', array($this, 'mapShortcode'));
            add_shortcode('pgm-list', array($this, 'listShortcode'));

            add_action('right_now_content_table_end', array($this, 'cpt_in_right_now'));

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
            // Save default settings
            if (!get_option(self::PREFIX . 'map-width'))
                add_option(self::PREFIX . 'map-width', 600);
            if (!get_option(self::PREFIX . 'map-height'))
                add_option(self::PREFIX . 'map-height', 400);
            if (!get_option(self::PREFIX . 'map-address'))
                add_option(self::PREFIX . 'map-address', 'Seattle');
            if (!get_option(self::PREFIX . 'map-latitude'))
                add_option(self::PREFIX . 'map-latitude', 47.6062095);
            if (!get_option(self::PREFIX . 'map-longitude'))
                add_option(self::PREFIX . 'map-longitude', -122.3320708);
            if (!get_option(self::PREFIX . 'map-zoom'))
                add_option(self::PREFIX . 'map-zoom', 7);
            if (!get_option(self::PREFIX . 'map-info-window-width'))
                add_option(self::PREFIX . 'map-info-window-width', 500);
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
         * Queues up a message to be displayed to the user
         * @author Diego Montoto <dmontoto@gmail.com>
         * @param string $message The text to show the user
         * @param string $type 'update' for a success or notification message, or 'error' for an error message
         * @param string $mode 'user' if it's intended for the user, or 'debug' if it's intended for the developer
         */
        protected function enqueueMessage($message, $type = 'update', $mode = 'user') {
            if (!is_string($message))
                return false;

            array_push($this->options[$type . 's'], array(
                'message' => $message,
                'type' => $type,
                'mode' => $mode
            ));

            if ($mode == 'user')
                $this->userMessageCount[$type . 's']++;

            $this->updatedOptions = true;

            return true;
        }

        /**
         * Checks the current posts to see if they contain the map shortcode
         * @author Diego Montoto <dmontoto@gmail.com>
         * @return bool
         */
        function mapShortcodeCalled() {
            global $post;
            $matches = array();

            $this->mapShortcodeCalled = apply_filters(self::PREFIX . 'mapShortcodeCalled', $this->mapShortcodeCalled);
            if ($this->mapShortcodeCalled)
                return true;

            preg_match_all('/' . get_shortcode_regex() . '/s', $post->post_content, $matches);

            if (is_array($matches) && array_key_exists(2, $matches) && in_array('pgm-map', $matches[2]))
                return true;

            return false;
        }

        /**
         * Load CSS and JavaScript files
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function loadResources() {
            wp_register_script('googleMapsAPI', 'http' . ( is_ssl() ? 's' : '' ) . '://maps.google.com/maps/api/js?sensor=false', false, false, true);

            wp_register_script('pgm', plugins_url('/javascript/pgm_gmaps.js', __FILE__), array('googleMapsAPI', 'jquery'), self::VERSION, true);

            wp_register_style(self::PREFIX . 'style', plugins_url('/css/style.css', __FILE__), false, self::VERSION, false);

            $this->mapShortcodeCalled = $this->mapShortcodeCalled();

            if (!is_admin() && $this->mapShortcodeCalled) {
                wp_enqueue_script('googleMapsAPI');
                wp_enqueue_script('pgm');

                $pgmData = sprintf("pgmData.options = %s;\r\npgmData.markers = %s", json_encode($this->getMapOptions()), json_encode($this->getPlacemarks()));

                wp_localize_script('pgm', 'pgmData', array('l10n_print_after' => $pgmData));
            }

            if (is_admin() || $this->mapShortcodeCalled) {
                wp_enqueue_style(self::PREFIX . 'style');
            }
        }

        /**
         * Outputs elements in the <head> section of the front-end
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function outputHead() {
            if ($this->mapShortcodeCalled)
                require_once( dirname(__FILE__) . '/views/front-end-head.php' );
        }

        /**
         * Geocodes an address
         * Google's API has a daily request limit, but this is only called when a post is published, so shouldn't ever be a problem.
         * @param
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function geocode($address) {
            $geocodeResponse = wp_remote_get('http://maps.googleapis.com/maps/api/geocode/json?address=' . str_replace(' ', '+', $address) . '&sensor=false');
            $coordinates = json_decode($geocodeResponse['body']);

            if (is_wp_error($geocodeResponse) || empty($coordinates->results))
                return false;
            else
                return array('latitude' => $coordinates->results[0]->geometry->location->lat, 'longitude' => $coordinates->results[0]->geometry->location->lng);
        }

        /**
         * Reverse-geocodes a set of coordinates
         * Google's API has a daily request limit, but this is only called when a post is published, so shouldn't ever be a problem.
         * @param string $latitude
         * @param string $longitude
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        protected function reverseGeocode($latitude, $longitude) {
            $geocodeResponse = wp_remote_get('http://maps.googleapis.com/maps/api/geocode/json?latlng=' . $latitude . ',' . $longitude . '&sensor=false');
            $address = json_decode($geocodeResponse['body']);

            if (is_wp_error($geocodeResponse) || empty($address->results))
                return false;
            else
                return $address->results[0]->formatted_address;
        }

        /**
         * Defines the [pgm-map] shortcode
         * @author Diego Montoto <dmontoto@gmail.com>
         * @param array $attributes Array of parameters automatically passed in by Wordpress
         * return string The output of the shortcode
         */
        public function mapShortcode($attributes) {
            if (!wp_script_is('googleMapsAPI', 'queue') || !wp_script_is('pgm', 'queue') || !wp_style_is(self::PREFIX . 'style', 'queue'))
                return '<p class="error">' . BGMP_NAME . ' error: JavaScript and/or CSS files aren\'t loaded. If you\'re using do_shortcode() you need to add a filter to your theme first. See <a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/">the FAQ</a> for details.</p>';

            $output = sprintf('
				<div id="%smap-canvas">
					<p>Loading map...</p>
					<p><img src="%s" alt="Loading" /></p>
				</div>', self::PREFIX, plugins_url('images/loading.gif', __FILE__)
            );

            return $output;
        }

        /**
         * Defines the [pgm-list] shortcode
         * @author Diego Montoto <dmontoto@gmail.com>
         * @param array $attributes Array of parameters automatically passed in by Wordpress
         * return string The output of the shortcode
         */
        public function listShortcode($attributes) {
            $posts = get_posts(array(
                'numberposts' => -1,
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
                    ));

            if ($posts) {
                $output = '<ul id="' . self::PREFIX . 'list">';

                foreach ($posts as $p) {
                    $address = get_post_meta($p->ID, self::PREFIX . 'address', true);

                    $output .= sprintf('
						<li>
							<h3>%s</h3>
							<div>%s</div>
							<p><a href="%s">%s</a></p>
						</li>', $p->post_title, nl2br($p->post_content), 'http://google.com/maps?q=' . $address, $address
                    );
                }

                $output .= '</ul>';

                return $output;
            }
            else
                return "There aren't any published placemarks right now";
        }

        /**
         * Gets map options
         * json_encode() requires PHP 5.
         * @author Diego Montoto <dmontoto@gmail.com>
         * @return string JSON-encoded array
         */
        public function getMapOptions() {
            $options = array(
                'mapWidth' => $this->settings->mapWidth,
                'mapHeight' => $this->settings->mapHeight,
                'latitude' => $this->settings->mapLatitude,
                'longitude' => $this->settings->mapLongitude,
                'zoom' => $this->settings->mapZoom,
                'infoWindowMaxWidth' => $this->settings->mapInfoWindowMaxWidth
            );

            return $options;
        }

        /**
         * Gets the published placemarks from the database, formats and outputs them.
         * json_encode() requires PHP 5.
         * @author Diego Montoto <dmontoto@gmail.com>
         * @return string JSON-encoded array
         */
        public function getPlacemarks() {
            $placemarks = array();
            $publishedPlacemarks = get_posts(array('numberposts' => -1, 'post_type' => 'pgm_camping', 'post_status' => 'publish'));

            if ($publishedPlacemarks) {
                foreach ($publishedPlacemarks as $pp) {
                    $icon = wp_get_attachment_image_src(get_post_thumbnail_id($pp->ID));

                    $placemarks[] = array(
                        'title' => $pp->post_title,
                        'latitude' => get_post_meta($pp->ID, 'pgm_camping_' . 'latitude', true),
                        'longitude' => get_post_meta($pp->ID, 'pgm_camping_' . 'longitude', true),
                        'details' => nl2br($pp->post_content),
                        'icon' => is_array($icon) ? $icon[0] : plugins_url('images/default-marker.png', __FILE__),
                        'zIndex' => get_post_meta($pp->ID, 'pgm_camping_' . 'zIndex', true),
                    );
                }
            }

            return $placemarks;
        }

        /**
         * Displays updates and errors
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function printMessages() {
            foreach (array('updates', 'errors') as $type) {
                if ($this->options[$type] && ( self::DEBUG_MODE || $this->userMessageCount[$type] )) {
                    echo '<div id="message" class="' . ( $type == 'updates' ? 'updated' : 'error' ) . '">';
                    foreach ($this->options[$type] as $message)
                        if ($message['mode'] == 'user' || self::DEBUG_MODE)
                            echo '<p>' . $message['message'] . '</p>';
                    echo '</div>';

                    $this->options[$type] = array();
                    $this->updatedOptions = true;
                    $this->userMessageCount[$type] = 0;
                }
            }
        }

        /**
         * Stops execution and prints the input. Used for debugging.
         * @author Diego Montoto <dmontoto@gmail.com>
         * @param mixed $data
         * @param string $output 'echo' | 'notice' (will create admin_notice ) | 'die' | 'return'
         * @param string $message Optionally message to output before description
         */
        protected function describe($data, $output = 'die', $message = '') {
            $type = gettype($data);

            switch ($type) {
                case 'array':
                case 'object':
                    $length = count($data);
                    $data = print_r($data, true);
                    break;

                case 'string';
                    $length = strlen($data);
                    break;

                default:
                    $length = count($data);

                    ob_start();
                    var_dump($data);
                    $data = ob_get_contents();
                    ob_end_clean();

                    $data = print_r($data, true);

                    break;
            }

            $description = sprintf('
				<p>
					%s
					Type: %s<br />
					Length: %s<br />
					Content: <br /><blockquote><pre>%s</pre></blockquote>
				</p>', ( $message ? 'Message: ' . $message . '<br />' : ''), $type, $length, $data
            );

            switch ($output) {
                case 'notice':
                    $this->enqueueMessage($description, 'error');
                    break;
                case 'output':
                    return $description;
                    break;
                case 'echo':
                    echo $description;
                    break;
                case 'die':
                default:
                    wp_die($description);
                    break;
            }
        }

        public function cpt_in_right_now() {
            ?>
            </table>
            <p class="sub poi_sub"><?php _e('Poi Content', 'poithemes'); ?></p>
            <table>
                <tr>
                    <td class="first b"><a href="edit.php?post_type=pgm_camping"><?php
            $num_posts = wp_count_posts('pgm_camping');
            $num = number_format_i18n($num_posts->publish);
            echo $num;
            ?></a></td>
                    <td class="t"><a href="edit-tags.php?post_type=pgm_camping"><?php _e('Campings', 'poithemes'); ?></a></td>
                </tr>
                <tr>
                    <td class="first b"><a href="edit.php?taxonomy=region&post_type=pgm_camping"><?php
                echo wp_count_terms('region');
            ?></a></td>
                    <td class="t"><a href="edit-tags.php?taxonomy=region&post_type=pgm_camping"><?php _e('Regions', 'poithemes'); ?></a></td>
                </tr>
                <?php
            }

            /**
             * Regeneramos las rewrite rules
             */
            public function my_rewrite_flush() {
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
