<?php

if ($_SERVER['SCRIPT_FILENAME'] == __FILE__)
    die("Access denied.");

if (!class_exists('Poi_Region')) {

    /**
     * A Wordpress plugin that adds a custom post type for placemarks and builds a Google Map with them
     * Requires PHP5+ because of various OOP features, json_encode(), pass by reference, etc
     * Requires Wordpress 3.0 because of custom post type support
     *
     * @package PoiGoogleMaps
     * @author Diego Montoto <dmontoto@gmail.com>
     * @link 
     */
    class Poi_Region {

        /**
         * Constructor
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function __construct() {
            add_action('init', array($this, 'createPostTaxonomies'));
        }

        /**
         * Registers custom Taxonomies
         * @author Diego Montoto <dmontoto@gmail.com>
         */
        public function createPostTaxonomies() {
            // Add new taxonomy, make it hierarchical (like categories)
            $labels = array(
                'name' => _x('Region', 'taxonomy general name'),
                'singular_name' => _x('Region', 'taxonomy singular name'),
                'search_items' => __('Search Regions'),
                'all_items' => __('All Regions'),
                'parent_item' => __('Parent Region'),
                'parent_item_colon' => __('Parent Region:'),
                'edit_item' => __('Edit Region'),
                'update_item' => __('Update Region'),
                'add_new_item' => __('Add New Region'),
                'new_item_name' => __('New Region Name'),
            );

            register_taxonomy('region', array('pgm_camping'), array(
                'hierarchical' => true,
                'public' => true,
                'label' => 'Region',
                'labels' => $labels,
                'show_ui' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'region'),
            ));

            //Register default values
            $this->RegisterTaxonomiesDefaultsValues($this);
        }

        private function RegisterTaxonomiesDefaultsValues($PoiGoogleMaps) {
            // Creamos los valores por defecto de las regiones
            $spainRegion = $PoiGoogleMaps->insert_term('Spain', 'region');
            $andaluciaRegion = $PoiGoogleMaps->insert_term('Andalucía', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Almería', 'region', array('parent' => $andaluciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Cádiz', 'region', array('parent' => $andaluciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Córdoba', 'region', array('parent' => $andaluciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Granada', 'region', array('parent' => $andaluciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Huelva', 'region', array('parent' => $andaluciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Jaén', 'region', array('parent' => $andaluciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Málaga', 'region', array('parent' => $andaluciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Sevilla', 'region', array('parent' => $andaluciaRegion['term_id']));
            $aragonRegion = $PoiGoogleMaps->insert_term('Aragón', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Huesca', 'region', array('parent' => $aragonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Teruel', 'region', array('parent' => $aragonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Zaragoza', 'region', array('parent' => $aragonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Asturias', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Baleares', 'region', array('parent' => $spainRegion['term_id']));
            $canariasRegion = $PoiGoogleMaps->insert_term('Canarias', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Las Palmas', 'region', array('parent' => $canariasRegion['term_id']));
            $PoiGoogleMaps->insert_term('Santa Cruz', 'region', array('parent' => $canariasRegion['term_id']));
            $PoiGoogleMaps->insert_term('Cantabria', 'region', array('parent' => $spainRegion['term_id']));
            $castillaManchaRegion = $PoiGoogleMaps->insert_term('Castilla La Mancha', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Albacete', 'region', array('parent' => $castillaManchaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Ciudad Real', 'region', array('parent' => $castillaManchaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Cuenca', 'region', array('parent' => $castillaManchaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Guadalajara', 'region', array('parent' => $castillaManchaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Toledo', 'region', array('parent' => $castillaManchaRegion['term_id']));
            $castillaLeonRegion = $PoiGoogleMaps->insert_term('Castilla León', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Avila', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Burgos', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('León', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Palencia', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Salamanca', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Segovia', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Soria', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Valladolid', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $PoiGoogleMaps->insert_term('Zamora', 'region', array('parent' => $castillaLeonRegion['term_id']));
            $catalunyaRegion = $PoiGoogleMaps->insert_term('Cataluña', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Barcelona', 'region', array('parent' => $catalunyaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Girona', 'region', array('parent' => $catalunyaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Lleida', 'region', array('parent' => $catalunyaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Tarragona', 'region', array('parent' => $catalunyaRegion['term_id']));
            $comunidadValencianaRegion = $PoiGoogleMaps->insert_term('Comunidad Valenciana', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Alicante', 'region', array('parent' => $comunidadValencianaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Castellón', 'region', array('parent' => $comunidadValencianaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Valencia', 'region', array('parent' => $comunidadValencianaRegion['term_id']));
            $extremaduraRegion = $PoiGoogleMaps->insert_term('Extremadura', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Badajoz', 'region', array('parent' => $extremaduraRegion['term_id']));
            $PoiGoogleMaps->insert_term('Cáceres', 'region', array('parent' => $extremaduraRegion['term_id']));
            $galiciaRegion = $PoiGoogleMaps->insert_term('Galicia', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('A Coruña', 'region', array('parent' => $galiciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Lugo', 'region', array('parent' => $galiciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Orense', 'region', array('parent' => $galiciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('Pontevedra', 'region', array('parent' => $galiciaRegion['term_id']));
            $PoiGoogleMaps->insert_term('La Rioja', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Madrid', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Murcia', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Navarra', 'region', array('parent' => $spainRegion['term_id']));
            $paisVascoRegion = $PoiGoogleMaps->insert_term('País Vasco', 'region', array('parent' => $spainRegion['term_id']));
            $PoiGoogleMaps->insert_term('Alava', 'region', array('parent' => $paisVascoRegion['term_id']));
            $PoiGoogleMaps->insert_term('Guipuzcoa', 'region', array('parent' => $paisVascoRegion['term_id']));
            $PoiGoogleMaps->insert_term('Vizcaya', 'region', array('parent' => $paisVascoRegion['term_id']));

            $franceRegion = $PoiGoogleMaps->insert_term('France', 'region');
        }

        /**
         * Register values in a taxonomy
         * @param type $term
         * @param type $taxonomy
         * @param type $args
         * @return type 
         */
        public function insert_term($term, $taxonomy, $args = array()) {
            if (isset($args['parent'])) {
                $parent = $args['parent'];
            } else {
                $parent = 0;
            }
            $result = term_exists($term, $taxonomy, $parent);
            if ($result == false || $result == 0) {
                return wp_insert_term($term, $taxonomy, $args);
            } else {
                return (array) $result;
            }
        }
        
    }
}
?>
