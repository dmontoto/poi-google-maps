/**
 * @package PoiGoogleMaps
 * @author Diego Montoto <dmontoto@gmail.com>
 * @link 
 */

 
/**
 * Wrapper function to safely use $
 * @author Diego Montoto <dmontoto@gmail.com>
 */
function pgmAdmin_wrapper( $ ) {
	var pgmAdmin = {
		/**
		 * Main entry point
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		init : function()
		{
			pgmAdmin.name = 'Poi Google Maps';
			pgmAdmin.canvas = document.getElementById("pgmAdmin_map-canvas"); // We have to use getElementById instead of a jQuery selector here in order to pass it to the Maps API.
			pgmAdmin.previousInfoWindow	= undefined;
			
			if( pgmAdmin.canvas )
				pgmAdmin.buildMap();
			else
				$( pgmAdmin.canvas ).html( pgmAdmin.name + " error: couldn't retrieve DOM elements.");
		},
		
		/**
		 * Pull in the map options from Wordpress' database and create the map
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		buildMap : function()
		{
			var mapOptions;
			
			if( pgmAdminData.options.mapWidth == '' || pgmAdminData.options.mapHeight == '' || pgmAdminData.options.latitude == '' || pgmAdminData.options.longitude == '' || pgmAdminData.options.zoom == '' || pgmAdminData.options.infoWindowMaxWidth == '' )
			{
				$( pgmAdmin.canvas ).html( pgmAdmin.name + " error: map options not set.");
				return;
			}
			
			mapOptions = 
			{
				'zoom'			: parseInt( pgmAdminData.options.zoom ),
				'center'		: new google.maps.LatLng( parseFloat(pgmAdminData.options.latitude), parseFloat(pgmAdminData.options.longitude) ),
				'mapTypeId'		: google.maps.MapTypeId.ROADMAP,
				'mapTypeControl'	: false
			};
			
			// Override default width/heights from settings
			$('#pgmAdmin_map-canvas').css('width', pgmAdminData.options.mapWidth );
			$('#pgmAdmin_map-canvas').css('height', pgmAdminData.options.mapHeight );
			
			// Create the map
			try
			{
				map = new google.maps.Map( pgmAdmin.canvas, mapOptions );
				pgmAdmin.addPlacemarks(map);
			}
			catch( e )
			{
				$( pgmAdmin.canvas ).html( pgmAdmin.name + " error: couln't build map." );
				if( window.console )
					console.log( 'pgmAdmin_buildMap: '+ e );
			}
		},
		
		/**
		 * Checks if the value is an integer. Slightly modified version of original.
		 * @author Invent Partners
		 * @link http://www.inventpartners.com/content/javascript_is_int
		 * @param mixed value
		 * @return bool
		 */
		isInt : function( value )
		{
			if( !isNaN( value ) && parseFloat( value ) == parseInt( value ) )
				return true;
			else
				return false;
		},

		/**
		 * Pull the placemark posts from Wordpress' database and add them to the map
		 * @author Diego Montoto <dmontoto@gmail.com>
		 * @param object map Google Maps map
		 */
		addPlacemarks : function( map )
		{
			// @todo - should probably refactor this since you pulled out the ajax. update phpdoc too
			
			if( pgmAdminData.markers.length > 0 )
				for( var m in pgmAdminData.markers )
					pgmAdmin.createMarker( map, pgmAdminData.markers[m]['title'], parseFloat( pgmAdminData.markers[m]['latitude'] ), parseFloat( pgmAdminData.markers[m]['longitude'] ), pgmAdminData.markers[m]['details'], pgmAdminData.markers[m]['icon'], parseInt( pgmAdminData.markers[m]['zIndex'] ) );
		},

		/**
		 * Create a draggable marker
		 * @author Diego Montoto <dmontoto@gmail.com>
		 * @param object map Google Maps map
		 * @param string title Placemark title
		 * @param float latituded
		 * @param float longitude
		 * @param string details Content of the infowinder
		 * @param string icon URL of the icon
		 * @param int zIndex The desired position in the placemark stacking order
		 * @return bool True on success, false on failure
		 */
		createMarker : function( map, title, latitude, longitude, details, icon, zIndex )
		{
			// @todo - clean up variable names
			
			var marker;
			
			if( isNaN( latitude ) || isNaN( longitude ) )
			{
				if( window.console )
					console.log( "pgmAdmin_createMarker(): "+ title +" latitude and longitude weren't valid." );
					
				return false;
			}
			
			if( icon == null )
			{
				// @todo - this check may not be needed anymore
				
				if( window.console )
					console.log( "pgmAdmin_createMarker(): "+ title +"  icon wasn't passed in." );
				return false;
			}
			
			if( !pgmAdmin.isInt( zIndex ) )
			{
				//if( window.console )
					//console.log( "pgm_createMarker():  "+ title +" z-index wasn't valid." );	// this would fire any time it's empty
				
				zIndex = 0;
			}
			
			try
			{
				marker = new google.maps.Marker( {
					'position':	new google.maps.LatLng( latitude, longitude ),
					'map':		map,
					'icon':		icon,
					'title':	title,
                                        'draggable':    true,
					'zIndex':	zIndex
				} );
                                
                                google.maps.event.addListener( marker, 'dragend', function() {
                                    var point = marker.getPosition();
                                    map.panTo(point);
                                    document.getElementById("pgm_camping_latitude").value = point.lat();
                                    document.getElementById("pgm_camping_longitude").value = point.lng();
				} );
				
				
				return true;
			}
			catch( e )
			{
				//$( pgm.canvas ).append( '<p>' + pgm.name + " error: couldn't add map placemarks.</p>");		// add class for making red? other places need this too?	// @todo - need to figure out a good way to alert user that placemarks couldn't be added
				if( window.console )
					console.log('pgmAdmin_createMarker: '+ e);
			}
		}
	} // end pgm
	
	// Kick things off...
	$(document).ready( pgmAdmin.init );
	
} // end pgmAdmin_wrapper()

pgmAdmin_wrapper(jQuery);