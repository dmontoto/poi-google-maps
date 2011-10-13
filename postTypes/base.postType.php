<?php

if ($_SERVER['SCRIPT_FILENAME'] == __FILE__)
    die("Access denied.");

if (!class_exists('Poi_Base')) {

    /**
     * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
     * Requires PHP5+ because of various OOP features, json_encode(), pass by reference, etc
     * Requires Wordpress 3.0 because of custom post type support
     *
     * @package PoiGoogleMaps
     * @author Diego Montoto <dmontoto@gmail.com>
     * @link 
     */
    abstract class Poi_Base {
        const DEBUG_MODE = false;
        public $PostType = 'pgm_base';
        public $PoiPrefix = 'pgm_base_';

        /**
         * Constructor
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function __construct($postType, $prefix) {
            $this->PostType = $postType;
            $this->PoiPrefix = $prefix;
            
            $defaultOptions = array('updates' => array(), 'errors' => array());
            $this->options = array_merge($defaultOptions, get_option($this->PoiPrefix . 'options', array()));
            $this->userMessageCount = array('updates' => count($this->options['updates']), 'errors' => count($this->options['errors']));
            $this->updatedOptions = false;

            // Register actions, filters
            add_action('init', array($this, 'createPostType'));
            add_action('save_post', array($this, 'saveCustomFields'), 1, 2);
            add_action('admin_notices', array($this, 'printMessages'));
            add_action('after_setup_theme', array($this, 'addFeaturedImageSupport'), 11);
            add_action('shutdown', array($this, 'shutdown'));
            
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

        abstract protected function singleActivate();
        
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

        /**
         * Registers the custom post type
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        abstract public function createPostType();
        
        /**
         * Saves values of the the custom post type's extra fields
         * @param
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        abstract public function saveCustomFields($postID, $post);

        /**
         * Adds featured image support
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function addFeaturedImageSupport() {
            // We enabled image media buttons for MultiSite on activation, but the admin may have turned it back off
            if (is_admin() && function_exists('is_multisite') && is_multisite()) {
                $mediaButtons = get_site_option('mu_media_buttons');

                if (!array_key_exists('image', $mediaButtons) || !$mediaButtons['image']) {
                    $this->enqueueMessage(
                        sprintf("%s requires the Images media button setting to be enabled in order to use custom icons on markers, but it's currently turned off. If you'd like to use custom icons you can enable it on the <a href=\"%ssettings.php\">Network Settings</a> page, in the Upload Settings section.", PGM_NAME, network_admin_url()), 'error');
                }
            }

            $supportedTypes = get_theme_support('post-thumbnails');

            if ($supportedTypes === false)
                add_theme_support('post-thumbnails', array($this->PostType));
            elseif (is_array($supportedTypes)) {
                $supportedTypes[0][] = $this->PostType;
                add_theme_support('post-thumbnails', $supportedTypes[0]);
            }
        }
        
        /**
         * Regeneramos las rewrite rules
         */
        public function my_rewrite_flush() {
            $this->createPostType();
            flush_rewrite_rules();
        }
        
        /**
         * Writes options to the database
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function shutdown() {
            if (is_admin())
                if ($this->updatedOptions)
                    update_option($this->PoiPrefix . 'options', $this->options);
        }
    }

}
?>
