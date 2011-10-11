<?php

if( $_SERVER['SCRIPT_FILENAME'] == __FILE__ )
	die("Access denied.");

if( !class_exists('PoiGoogleMaps') )
{
	/**
	 * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
	 * Requires PHP5+ because of various OOP features, json_encode(), pass by reference, etc
	 * Requires Wordpress 3.0 because of custom post type support
	 *
	 * @package PoiGoogleMaps
	 * @author Diego Montoto <dmontoto@gmail.com>
	 * @link 
	 */
	class PoiGoogleMaps
	{
		// Declare variables and constants
		protected $settings, $options, $updatedOptions, $userMessageCount, $mapShortcodeCalled;
		const VERSION		= '0.2';
		const PREFIX		= 'pgm_';
		const POST_TYPE		= 'pgm';
		const DEBUG_MODE	= false;
		
		/**
		 * Constructor
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function __construct()
		{
			require_once( dirname(__FILE__) . '/settings.php');
			
			// Initialize variables
			$defaultOptions			= array( 'updates' => array(), 'errors' => array() );
			$this->options			= array_merge( $defaultOptions, get_option( self::PREFIX . 'options', array() ) );
			$this->userMessageCount		= array( 'updates' => count( $this->options['updates'] ), 'errors' => count( $this->options['errors'] )	);
			$this->updatedOptions		= false;
			$this->mapShortcodeCalled	= false;
			$this->settings			= new PGMSettings( $this );
			
			// Register actions, filters and shortcodes
			add_action( 'admin_notices',		array( $this, 'printMessages') );
			add_action( 'init',			array( $this, 'createPostType') );
                        add_action( 'init',			array( $this, 'createPostTaxonomies') );
			add_action( 'after_setup_theme', 	array( $this, 'addFeaturedImageSupport' ), 11 );
			add_action( 'admin_init',		array( $this, 'addMetaBoxes') );
			add_action( 'wp',			array( $this, 'loadResources' ), 11 );
			add_action( 'wp_head',			array( $this, 'outputHead' ) );
			add_action( 'save_post',		array( $this, 'saveCustomFields') );
			add_action( 'wpmu_new_blog', 		array( $this, 'activateNewSite' ) );
			add_action( 'shutdown',			array( $this, 'shutdown' ) );
			
			add_filter( 'parse_query',		array($this, 'sortAdminView' ) );
                        
			add_shortcode( 'pgm-map',		array( $this, 'mapShortcode') );
			add_shortcode( 'pgm-list',		array( $this, 'listShortcode') );
			
                        // Gestion de listado de administracion
                        add_filter( 'manage_edit-'.self::POST_TYPE.'_columns',          array($this, 'add_new_pgm_columns' ) );
                        add_action( 'manage_'.self::POST_TYPE.'_posts_custom_column',   array($this, 'manage_pgm_columns' ) );
                        add_filter( 'manage_edit-'.self::POST_TYPE.'_sortable_columns', array($this, 'sortable_pgm_columns' ) );
                        add_filter( 'request',                                          array($this, 'column_orderby' ) );
                        
                        // Add filtros de taxonomy al listado de administracion
                        add_action('restrict_manage_posts',     array($this, 'todo_restrict_manage_posts' ));
                        add_filter('parse_query',               array($this, 'todo_convert_restrict' ));
                        
			register_activation_hook( dirname(__FILE__) . '/poi-google-maps.php', array( $this, 'networkActivate') );
                        register_activation_hook( dirname(__FILE__) . '/poi-google-maps.php', array( $this, 'my_rewrite_flush'));
		}
                
		/**
		 * Handles extra activation tasks for MultiSite installations
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function networkActivate()
		{
			global $wpdb;
			
			if( function_exists('is_multisite') && is_multisite() )
			{
				// Enable image uploads so the 'Set Featured Image' meta box will be available
				$mediaButtons = get_site_option( 'mu_media_buttons' );
				
				if( !array_key_exists( 'image', $mediaButtons ) || !$mediaButtons['image'] )
				{
					$mediaButtons['image'] = 1;
					update_site_option( 'mu_media_buttons', $mediaButtons );
				}
				
				// Activate the plugin across the network if requested
				if( array_key_exists( 'networkwide', $_GET ) && ( $_GET['networkwide'] == 1) )
				{
					$blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
					
					foreach( $blogs as $b ) 
					{
						switch_to_blog( $b );
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
		protected function singleActivate()
		{
			// Save default settings
			if( !get_option( self::PREFIX . 'map-width' ) )
				add_option( self::PREFIX . 'map-width', 600 );
			if( !get_option( self::PREFIX . 'map-height' ) )
				add_option( self::PREFIX . 'map-height', 400 );
			if( !get_option( self::PREFIX . 'map-address' ) )
				add_option( self::PREFIX . 'map-address', 'Seattle' );
			if( !get_option( self::PREFIX . 'map-latitude' ) )
				add_option( self::PREFIX . 'map-latitude', 47.6062095 );
			if( !get_option( self::PREFIX . 'map-longitude' ) )
				add_option( self::PREFIX . 'map-longitude', -122.3320708 );
			if( !get_option( self::PREFIX . 'map-zoom' ) )
				add_option( self::PREFIX . 'map-zoom', 7 );
			if( !get_option( self::PREFIX . 'map-info-window-width' ) )
				add_option( self::PREFIX . 'map-info-window-width', 500 );
				
			// Upgrade 1.0 placemark data
			$posts = get_posts( array( 'numberposts' => -1, 'post_type' => self::POST_TYPE ) );
			if( $posts )
			{
				foreach( $posts as $p )
				{
					$address	= get_post_meta( $p->ID, self::PREFIX . 'address', true );
					$latitude	= get_post_meta( $p->ID, self::PREFIX . 'latitude', true );
					$longitude	= get_post_meta( $p->ID, self::PREFIX . 'longitude', true );
					
					if( empty($address) && !empty($latitude) && !empty($longitude) )
					{
						$address = $this->reverseGeocode( $latitude, $longitude );
						if( $address )
							update_post_meta( $p->ID, self::PREFIX . 'address', $address );
					}
				}
			}
		}
                
		/**
		 * Runs activation code on a new WPMS site when it's created
		 * @author Diego Montoto <dmontoto@gmail.com>
		 * @param int $blogID
		 */
		public function activateNewSite( $blogID )
		{
			switch_to_blog( $blogID );
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
		protected function enqueueMessage( $message, $type = 'update', $mode = 'user' )
		{
			if( !is_string( $message ) )
				return false;
			
			array_push( $this->options[$type .'s'], array(
				'message' => $message,
				'type' => $type,
				'mode' => $mode
			) );
			
			if( $mode == 'user' )
				$this->userMessageCount[$type . 's']++;
			
			$this->updatedOptions = true;
			
			return true;
		}
                
		/**
		 * Adds featured image support
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function addFeaturedImageSupport()
                {
			// We enabled image media buttons for MultiSite on activation, but the admin may have turned it back off
			if( is_admin() && function_exists('is_multisite') && is_multisite() )
			{
				$mediaButtons = get_site_option( 'mu_media_buttons' );
				
				if( !array_key_exists( 'image', $mediaButtons ) || !$mediaButtons['image'] )
				{
					$this->enqueueMessage( sprintf(
						"%s requires the Images media button setting to be enabled in order to use custom icons on markers, but it's currently turned off. If you'd like to use custom icons you can enable it on the <a href=\"%ssettings.php\">Network Settings</a> page, in the Upload Settings section.",
						PGM_NAME,
						network_admin_url()
					), 'error' );
				}
			}
			
			$supportedTypes = get_theme_support( 'post-thumbnails' );
			
			if( $supportedTypes === false )
				add_theme_support( 'post-thumbnails', array( self::POST_TYPE ) );				
			elseif( is_array( $supportedTypes ) )
			{
				$supportedTypes[0][] = self::POST_TYPE;
				add_theme_support( 'post-thumbnails', $supportedTypes[0] );
			}
		}
                
		/**
		 * Checks the current posts to see if they contain the map shortcode
		 * @author Diego Montoto <dmontoto@gmail.com>
		 * @return bool
		 */
		function mapShortcodeCalled()
		{
			global $post;
			$matches = array();
			
			$this->mapShortcodeCalled = apply_filters( self::PREFIX .'mapShortcodeCalled', $this->mapShortcodeCalled );
			if( $this->mapShortcodeCalled )
				return true;
			
			preg_match_all( '/'. get_shortcode_regex() .'/s', $post->post_content, $matches );
			
			if( is_array( $matches ) && array_key_exists( 2, $matches ) && in_array( 'pgm-map', $matches[2] ) )
				return true;
			
			return false;
		}
                
		/**
		 * Load CSS and JavaScript files
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function loadResources()
		{
			wp_register_script(
				'googleMapsAPI',
				'http'. ( is_ssl() ? 's' : '' ) .'://maps.google.com/maps/api/js?sensor=false',
				false,
				false,
				true
			);
			
			wp_register_script(
				'pgm',
				plugins_url( 'functions.js', __FILE__ ),
				array( 'googleMapsAPI', 'jquery' ),
				self::VERSION,
				true
			);
			
			wp_register_style(
				self::PREFIX .'style',
				plugins_url( 'style.css', __FILE__ ),
				false,
				self::VERSION,
				false
			);
			
			$this->mapShortcodeCalled = $this->mapShortcodeCalled();
			
			if( !is_admin() && $this->mapShortcodeCalled )
			{
				wp_enqueue_script('googleMapsAPI');
				wp_enqueue_script('pgm');
				
				$pgmData = sprintf(
					"pgmData.options = %s;\r\npgmData.markers = %s",
					json_encode( $this->getMapOptions() ),
					json_encode( $this->getPlacemarks() )
				);
				
				wp_localize_script( 'pgm', 'pgmData', array( 'l10n_print_after' => $pgmData ) );
			}
			
			if( is_admin() || $this->mapShortcodeCalled )
				wp_enqueue_style( self::PREFIX . 'style' );
		}
                
		/**
		 * Outputs elements in the <head> section of the front-end
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function outputHead()
		{
			if( $this->mapShortcodeCalled )
				require_once( dirname(__FILE__) . '/views/front-end-head.php' );
		}
                
		/**
		 * Registers the custom post type
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function createPostType()
		{
                    if( !post_type_exists( self::POST_TYPE ) ) {
                        $labels = array	(
                            'name'                  => __( 'Pois' ),
                            'singular_name'         => __( 'Poi' ),
                            'add_new'               => __( 'Add New' ),
                            'add_new_item'          => __( 'Add New Poi' ),
                            'edit'                  => __( 'Edit' ),
                            'edit_item'             => __( 'Edit Poi' ),
                            'new_item'              => __( 'New Poi' ),
                            'view'                  => __( 'View Poi' ),
                            'view_item'             => __( 'View Poi' ),
                            'search_items'          => __( 'Search Pois' ),
                            'not_found'             => __( 'No Pois found' ),
                            'not_found_in_trash'    => __( 'No Pois found in Trash' ),
                            'parent'                => __( 'Parent Poi' )
                        );
				
                        register_post_type(
                            self::POST_TYPE,
                            array (
                                'labels'		=> $labels,
                                'singular_label'	=> 'Pois',
                                'public'		=> true,
                                'menu_position'		=> 10,
                                'hierarchical'		=> false,
                                'capability_type'	=> 'post',
                                'rewrite'		=> array( 'slug' => 'pois', 'with_front' => false ),
                                'query_var'		=> true,
                                'supports'		=> array( 'title', 'editor', 'excerpt', 'custom-fields', 'thumbnail' ),
                                'taxonomies'		=> array( 'region' ),
                            )
                        );
                    }
		}
                
                /**
                 * Registers custom Taxonomies
                 * @author Diego Montoto <dmontoto@gmail.com>
                 */
                public function createPostTaxonomies()
                {
                    // Add new taxonomy, make it hierarchical (like categories)
                    $labels = array(
                        'name'              => _x( 'Region', 'taxonomy general name' ),
                        'singular_name'     => _x( 'Region', 'taxonomy singular name' ),
                        'search_items'      => __( 'Search Regions' ),
                        'all_items'         => __( 'All Regions' ),
                        'parent_item'       => __( 'Parent Region' ),
                        'parent_item_colon' => __( 'Parent Region:' ),
                        'edit_item'         => __( 'Edit Region' ),
                        'update_item'       => __( 'Update Region' ),
                        'add_new_item'      => __( 'Add New Region' ),
                        'new_item_name'     => __( 'New Region Name' ),
                    ); 
                    
                    register_taxonomy( 'region', array( self::POST_TYPE ), array(
                        'hierarchical'  => true,
                        'public'        => true,
                        'label'         => 'Region',
                        'labels'        => $labels, 
                        'show_ui'       => true,
                        'query_var'     => true,
                        'rewrite'       => array( 'slug' => 'region' ),
                    ));

                }

		/**
		 * Adds meta boxes for the custom post type
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function addMetaBoxes()
		{
			add_meta_box( self::PREFIX . 'poi-address', 'Poi Address', array($this, 'markupAddressFields'), self::POST_TYPE, 'normal', 'high' );
			add_meta_box( self::PREFIX . 'poi-zIndex', 'Stacking Order', array($this, 'markupZIndexField'), self::POST_TYPE, 'side', 'default' );
                        add_meta_box( self::PREFIX . 'attachments', 'Fotos', array($this, 'markupAttachments'), self::POST_TYPE, 'normal', 'high');
                        add_meta_box( self::PREFIX . 'videos', 'Videos', array($this, 'markupVideos'), self::POST_TYPE, 'normal', 'high');
		}

		/**
		 * Outputs the markup for the address fields
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function markupAddressFields()
		{
			global $post;
		
			$address	= get_post_meta( $post->ID, self::PREFIX . 'address', true );
			$latitude	= get_post_meta( $post->ID, self::PREFIX . 'latitude', true );
			$longitude	= get_post_meta( $post->ID, self::PREFIX . 'longitude', true );
			
			require_once( dirname(__FILE__) . '/views/meta-address.php' );
		}
                
		/**
		 * Outputs the markup for the stacking order field
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function markupZIndexField()
		{
			global $post;
		
			$zIndex = get_post_meta( $post->ID, self::PREFIX . 'zIndex', true );
			if( filter_var( $zIndex, FILTER_VALIDATE_INT ) === FALSE )
				$zIndex = 0;
				
			require_once( dirname(__FILE__) . '/views/meta-z-index.php' );
		}
                
                public function markupAttachments()
                {
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

                public function markupVideos()
                {
                    global $post;

                    $youtubeRegex = '%.*\?v=([a-zA-Z0-9\-_]+).*%i';
                    $vimeoRegex = '/^http:\/\/(www\.)?vimeo\.com\/(clip\:)?(\d+).*$/';

                    //We get an array with every videos attached to restaurant ( single=false )
                    $videos = get_post_meta( $post->ID, self::PREFIX . 'videos', false ); 

                    //Show videos' thumbnails
                    $video_id = array();
                    foreach ($videos as $video) {
                        if ( filter_var( $video, FILTER_VALIDATE_URL ) !== FALSE 
                            && 
                                ( preg_match($youtubeRegex, $video, $results) != FALSE //We use != instead of !== because we don't want FALSE, nor 0
                                || preg_match($vimeoRegex, $video, $results) != FALSE  ) 
                           ) {

                            $videoUrl = $video;
                            if ( preg_match($youtubeRegex, $video, $results) != FALSE )
                                $videoImg = 'http://img.youtube.com/vi/'.$results[1].'/default.jpg';
                            elseif ( preg_match($vimeoRegex, $video, $results) != FALSE ) {
                                $vimeoId = $results[3];
                                $hash = unserialize(file_get_contents('http://vimeo.com/api/v2/video/'.$vimeoId.'.php'));
                                $videoImg = $hash[0]['thumbnail_medium'];
                            }

                            printf('<a href="%s" target="_blank"><img src="%s"></a>',
                                $video,
                                $videoImg);
                        }
                    }

                    //Show new video's form
                    require_once( dirname(__FILE__) . '/views/meta-videos.php' );
                }
                
		/**
		 * Saves values of the the custom post type's extra fields
		 * @param
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function saveCustomFields( $postID )
		{
			global $post;
			$coordinates = false;
			
			if($post && $post->post_type == self::POST_TYPE && current_user_can( 'edit_posts' ) && $_GET['action'] != 'trash' && $_GET['action'] != 'untrash' )
			{
				if( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' )
					return;
				
				// Save address
				update_post_meta( $post->ID, self::PREFIX . 'address', $_POST[ self::PREFIX . 'address'] );
	
				if( $_POST[ self::PREFIX . 'address'] )
					$coordinates = $this->geocode( $_POST[ self::PREFIX . 'address'] );
					
				if( $coordinates )
				{
					update_post_meta( $post->ID, self::PREFIX . 'latitude', $coordinates['latitude'] );
					update_post_meta( $post->ID, self::PREFIX . 'longitude', $coordinates['longitude'] );
				}
				else
				{	
					update_post_meta( $post->ID, self::PREFIX . 'latitude', '' );
					update_post_meta( $post->ID, self::PREFIX . 'longitude', '' );
					
					if( !empty( $_POST[ self::PREFIX . 'address'] ) )
						$this->enqueueMessage('That address couldn\'t be geocoded, please make sure that it\'s correct.', 'error' );
				}
				
				// Save z-index
				if( filter_var( $_POST[ self::PREFIX . 'zIndex'], FILTER_VALIDATE_INT ) === FALSE )
				{
					update_post_meta( $post->ID, self::PREFIX . 'zIndex', 0 );
					$this->enqueueMessage( 'The stacking order has to be an integer', 'error' );
				}	
				else
					update_post_meta( $post->ID, self::PREFIX . 'zIndex', $_POST[ self::PREFIX . 'zIndex'] );
                                
                                // Save video
                                if( filter_var( $_POST[ self::PREFIX . 'video'], FILTER_VALIDATE_URL ) === FALSE )
                                {
                                    //update_post_meta( $post->ID, self::PREFIX . 'video', 0 );
                                    $this->enqueueMessage( 'El video tiene que ser una URL válida', 'error' );
                                }	
                                elseif ( preg_match('%.*?v=([a-z0-9\-_]+).*%', $_POST[ self::PREFIX . 'videos']) !== FALSE )
                                    add_post_meta( $post->ID, self::PREFIX . 'videos', $_POST[ self::PREFIX . 'video'], false );
			}
		}
                
		/**
		 * Sorts the posts by the title in the admin view posts screen
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		function sortAdminView( $query )
		{
			global $pagenow;
			
			if( is_admin() && $pagenow == 'edit.php' && array_key_exists('post_type', $_GET) && $_GET['post_type'] == self::POST_TYPE )
			{
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
                    $new_columns['title'] = _x('Poi Name', 'column name');
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
 
                            if ( !empty($terms) ) {
                                foreach ( $terms as $term )
                                    $post_terms[] = "<a href='edit.php?post_type={$post_type}&region=".$term->term_id."&action=-1&mode=list&action2=-1'> " . esc_html(sanitize_term_field('name', $term->name, $term->term_id, $taxonomy, 'edit')) . "</a>";
                                    echo join( ', ', $post_terms );
                            }
                            else echo '<i>No terms.</i>';
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
                    if ( isset( $vars['orderby'] )){
                        switch ($vars['orderby']) {
                            case 'region':
                                $vars = array_merge( $vars, array(
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
                function todo_restrict_manage_posts(){
                    global $typenow;
                    $args=array( 'public' => true, '_builtin' => false ); 
                    $post_types = get_post_types($args);
                    if ( in_array($typenow, $post_types) ) {
                        $filters = get_object_taxonomies($typenow);
                        foreach ($filters as $tax_slug) {
                            $tax_obj = get_taxonomy($tax_slug);
                            wp_dropdown_categories(array(
                                'show_option_all' => __('Show All '.$tax_obj->label ),
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
                function todo_convert_restrict($query){
                    global $pagenow;
                    global $typenow;
                    if ($pagenow=='edit.php') {
                        // Aplicamos los filtros
                        $filters = get_object_taxonomies($typenow);
                        foreach ($filters as $tax_slug) {
                            $var = &$query->query_vars[$tax_slug];
                            if ( isset($var) ) {
                                $term = get_term_by('id',$var,$tax_slug);
                                $var = $term->slug;
                            }
                        }
                        
                    }
                    
                }
                

                

                

                
		/**
		 * Geocodes an address
		 * Google's API has a daily request limit, but this is only called when a post is published, so shouldn't ever be a problem.
		 * @param
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function geocode( $address )
		{
			$geocodeResponse = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?address='. str_replace( ' ', '+', $address ) .'&sensor=false' );
			$coordinates = json_decode( $geocodeResponse['body'] );
				
			if( is_wp_error($geocodeResponse) || empty($coordinates->results) )
				return false;
			else
				return array( 'latitude' => $coordinates->results[0]->geometry->location->lat, 'longitude' => $coordinates->results[0]->geometry->location->lng );
		}
                
		/**
		 * Reverse-geocodes a set of coordinates
		 * Google's API has a daily request limit, but this is only called when a post is published, so shouldn't ever be a problem.
		 * @param string $latitude
		 * @param string $longitude
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		protected function reverseGeocode( $latitude, $longitude )
		{
			$geocodeResponse = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?latlng='. $latitude .','. $longitude .'&sensor=false' );
			$address = json_decode( $geocodeResponse['body'] );
			
			if( is_wp_error($geocodeResponse) || empty($address->results) )
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
		public function mapShortcode( $attributes ) 
		{
			if( !wp_script_is( 'googleMapsAPI', 'queue' ) || !wp_script_is( 'pgm', 'queue' ) || !wp_style_is( self::PREFIX .'style', 'queue' ) )
				return '<p class="error">'. BGMP_NAME .' error: JavaScript and/or CSS files aren\'t loaded. If you\'re using do_shortcode() you need to add a filter to your theme first. See <a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/">the FAQ</a> for details.</p>';
			
			$output = sprintf('
				<div id="%smap-canvas">
					<p>Loading map...</p>
					<p><img src="%s" alt="Loading" /></p>
				</div>',
				self::PREFIX,
				plugins_url( 'images/loading.gif', __FILE__ )
			);
			
			return $output;
		}
                
		/**
		 * Defines the [pgm-list] shortcode
		 * @author Diego Montoto <dmontoto@gmail.com>
		 * @param array $attributes Array of parameters automatically passed in by Wordpress
		 * return string The output of the shortcode
		 */
		public function listShortcode( $attributes ) 
		{
			$posts = get_posts( array(
				'numberposts'	=> -1,
				'post_type'	=> self::POST_TYPE,
				'post_status'	=> 'publish',
				'orderby'	=> 'title',
				'order'		=> 'ASC'
			) );
			
			if( $posts )
			{
				$output = '<ul id="'. self::PREFIX .'list">';
				
				foreach( $posts as $p )
				{
					$address = get_post_meta( $p->ID, self::PREFIX . 'address', true );
						
					$output .= sprintf('
						<li>
							<h3>%s</h3>
							<div>%s</div>
							<p><a href="%s">%s</a></p>
						</li>',
						$p->post_title,
						nl2br( $p->post_content ),
						'http://google.com/maps?q='. $address,
						$address
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
		public function getMapOptions()
		{
			$options = array(
				'mapWidth'		=> $this->settings->mapWidth,
				'mapHeight'		=> $this->settings->mapHeight,
				'latitude'		=> $this->settings->mapLatitude,
				'longitude'		=> $this->settings->mapLongitude,
				'zoom'			=> $this->settings->mapZoom,
				'infoWindowMaxWidth'    => $this->settings->mapInfoWindowMaxWidth
			);
		
			return $options;
		}
                
		/**
		 * Gets the published placemarks from the database, formats and outputs them.
		 * json_encode() requires PHP 5.
		 * @author Diego Montoto <dmontoto@gmail.com>
		 * @return string JSON-encoded array
		 */
		public function getPlacemarks()
		{
			$placemarks = array();
			$publishedPlacemarks = get_posts( array( 'numberposts' => -1, 'post_type' => self::POST_TYPE, 'post_status' => 'publish' ) );
			
			if( $publishedPlacemarks )
			{
				foreach( $publishedPlacemarks as $pp )
				{
					$icon = wp_get_attachment_image_src( get_post_thumbnail_id( $pp->ID ) );
 
					$placemarks[] = array(
						'title'		=> $pp->post_title,
						'latitude'	=> get_post_meta( $pp->ID, self::PREFIX . 'latitude', true ),
						'longitude'	=> get_post_meta( $pp->ID, self::PREFIX . 'longitude', true ),
						'details'	=> nl2br( $pp->post_content ),
						'icon'		=> is_array($icon) ? $icon[0] : plugins_url( 'images/default-marker.png', __FILE__ ),
						'zIndex'	=> get_post_meta( $pp->ID, self::PREFIX . 'zIndex', true ),
					);
				}
			}
			
			return $placemarks;
		}
                
		/**
		 * Displays updates and errors
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function printMessages()
		{
			foreach( array('updates', 'errors') as $type )
			{
				if( $this->options[$type] && ( self::DEBUG_MODE || $this->userMessageCount[$type] ) )
				{
					echo '<div id="message" class="'. ( $type == 'updates' ? 'updated' : 'error' ) .'">';
					foreach( $this->options[$type] as $message )
						if( $message['mode'] == 'user' || self::DEBUG_MODE )
							echo '<p>'. $message['message'] .'</p>';
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
		protected function describe( $data, $output = 'die', $message = '' )
		{
			$type = gettype( $data );

			switch( $type)
			{
				case 'array':
				case 'object':
					$length = count( $data );
					$data = print_r( $data, true );
					break;
				
				case 'string';
					$length = strlen( $data );
					break;
				
				default:
					$length = count( $data );
					
					ob_start();
					var_dump( $data );
					$data = ob_get_contents();
					ob_end_clean();
					
					$data = print_r( $data, true );
					
					break;
			}
			
			$description = sprintf('
				<p>
					%s
					Type: %s<br />
					Length: %s<br />
					Content: <br /><blockquote><pre>%s</pre></blockquote>
				</p>',
				( $message ? 'Message: '. $message .'<br />' : '' ),
				$type,
				$length,
				$data
			);
			
			switch( $output )
			{
				case 'notice':
					$this->enqueueMessage( $description, 'error' );
					break;
				case 'output':
					return $description;
					break;
				case 'echo':
					echo $description;
					break;
				case 'die':
				default:
					wp_die( $description );
					break;
			}
		}
                
                /**
                 * Regeneramos las rewrite rules
                 */
                public function my_rewrite_flush(){
                    createPostType();
                    flush_rewrite_rules();
                }
                
		/**
		 * Writes options to the database
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		public function shutdown()
		{
                    if( is_admin() )
                        if( $this->updatedOptions )
                            update_option( self::PREFIX . 'options', $this->options );
		}
                
        }
    
}
?>
