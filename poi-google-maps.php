<?php
/*
Plugin Name: Poi Google Maps
Plugin URI: 
Description: Muestra Pois en un mapa
Version: 0.2
Author: Diego Montoto Garcia
Author URI: 
License: GPL2
*/

/*  
 * Copyright 2011 Diego Montoto (email : dmontoto@gmail.com)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if( $_SERVER['SCRIPT_FILENAME'] == __FILE__ )
	die("Access denied.");

define( 'PGM_NAME', 'Poi Google Maps' );
define( 'PGM_REQUIRED_PHP_VERSON', '5.2' );
define( 'PGM_REQUIRED_WP_VERSION', '3.0' );

/**
 * Checks if the system requirements are met
 * @author Diego Montoto <dmontoto@gmail.com>
 * @return bool True if system requirements are met, false if not
 */
function pgm_requirementsMet()
{
	global $wp_version;
	
	if( version_compare(PHP_VERSION, PGM_REQUIRED_PHP_VERSON, '<') )
		return false;
	
	if( version_compare($wp_version, PGM_REQUIRED_WP_VERSION, "<") )
		return false;
	
	return true;
}

/**
 * Prints an error that the system requirements weren't met.
 * @author Diego Montoto <dmontoto@gmail.com>
 */
function pgm_requirementsNotMet()
{
	global $wp_version;
	
	echo sprintf('
		<div id="message" class="error">
			<p>
				%s <strong>requires PHP %s</strong> and <strong>WordPress %s</strong> in order to work. You\'re running PHP %s and WordPress %s. You\'ll need to upgrade in order to use this plugin. If you\'re not sure how to <a href="http://codex.wordpress.org/Switching_to_PHP5">upgrade to PHP 5</a> you can ask your hosting company for assistance, and if you need help upgrading WordPress you can refer to <a href="http://codex.wordpress.org/Upgrading_WordPress">the Codex</a>.
			</p>
		</div>',
		PGM_NAME,
		PGM_REQUIRED_PHP_VERSON,
		PGM_REQUIRED_WP_VERSION,
		PHP_VERSION,
		$wp_version		
	);
}

// Check requirements and instantiate
if( pgm_requirementsMet() )
{
	require_once( dirname(__FILE__) . '/core.php' );
	wp_enqueue_style('poi-admin-stylesheet', plugins_url('/css/poi.admin-general.css', __FILE__), array(), '1.0', 'screen');
        
	if( class_exists('PoiGoogleMaps') )
		$pgm = new PoiGoogleMaps();
}
else
	add_action( 'admin_notices', 'pgm_requirementsNotMet' );

?>