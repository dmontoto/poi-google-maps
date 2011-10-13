/**
 * @package PoiGoogleMaps
 * @author Diego Montoto <dmontoto@gmail.com>
 * @link 
 */

 
/**
 * Wrapper function to safely use $
 * @author Diego Montoto <dmontoto@gmail.com>
 */
function pgm_wrapper( $ )
{
	// @todo - figure out if wrapper bad for memory consumption (https://developer.mozilla.org/en/JavaScript/Reference/Functions_and_function_scope#Efficiency_considerations)
		// ask on stackoverflow
	
	var pgm = 
	{
		/**
		 * Main entry point
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		init : function()
		{
			pgm.name = 'Poi Google Maps';
			pgm.canvas = document.getElementById("pgm_map-canvas"); // We have to use getElementById instead of a jQuery selector here in order to pass it to the Maps API.
			pgm.previousInfoWindow	= undefined;
			
			if( pgm.canvas )
				pgm.buildMap();
			else
				$( pgm.canvas ).html( pgm.name + " error: couldn't retrieve DOM elements.");
		},
		
		/**
		 * Pull in the map options from Wordpress' database and create the map
		 * @author Diego Montoto <dmontoto@gmail.com>
		 */
		buildMap : function()
		{
			var mapOptions;
			
			if( pgmData.options.mapWidth == '' || pgmData.options.mapHeight == '' || pgmData.options.latitude == '' || pgmData.options.longitude == '' || pgmData.options.zoom == '' || pgmData.options.infoWindowMaxWidth == '' )
			{
				$( pgm.canvas ).html( pgm.name + " error: map options not set.");
				return;
			}
			
			mapOptions = 
			{
				'zoom'			: parseInt( pgmData.options.zoom ),
				'center'		: new google.maps.LatLng( parseFloat(pgmData.options.latitude), parseFloat(pgmData.options.longitude) ),
				'mapTypeId'		: google.maps.MapTypeId.ROADMAP,
				'mapTypeControl'	: false
			};
			
			// Override default width/heights from settings
			$('#pgm_map-canvas').css('width', pgmData.options.mapWidth );
			$('#pgm_map-canvas').css('height', pgmData.options.mapHeight );
			
			// Create the map
			try
			{
				map = new google.maps.Map( pgm.canvas, mapOptions );
				pgm.addPlacemarks(map);
			}
			catch( e )
			{
				$( pgm.canvas ).html( pgm.name + " error: couln't build map." );
				if( window.console )
					console.log( 'pgm_buildMap: '+ e );
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
			
			if( pgmData.markers.length > 0 )
				for( var m in pgmData.markers )
					pgm.createMarker( map, pgmData.markers[m]['title'], parseFloat( pgmData.markers[m]['latitude'] ), parseFloat( pgmData.markers[m]['longitude'] ), pgmData.markers[m]['details'], pgmData.markers[m]['icon'], parseInt( pgmData.markers[m]['zIndex'] ) );
		},

		/**
		 * Create a marker with an information window
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
			
			var infowindowcontent, infowindow, marker;
			
			if( isNaN( latitude ) || isNaN( longitude ) )
			{
				if( window.console )
					console.log( "pgm_createMarker(): "+ title +" latitude and longitude weren't valid." );
					
				return false;
			}
			
			if( icon == null )
			{
				// @todo - this check may not be needed anymore
				
				if( window.console )
					console.log( "pgm_createMarker(): "+ title +"  icon wasn't passed in." );
				return false;
			}
			
			if( !pgm.isInt( zIndex ) )
			{
				//if( window.console )
					//console.log( "pgm_createMarker():  "+ title +" z-index wasn't valid." );	// this would fire any time it's empty
				
				zIndex = 0;
			}
			
			infowindowcontent = '<div class="pgm_placemark"> <h1>'+ title +'</h1> <div>'+ details +'</div> </div>';
			
			try
			{
				infowindow = new google.maps.InfoWindow( {
					content:	infowindowcontent,
					maxWidth:	pgmData.options.infoWindowMaxWidth
				} );
				
				marker = new google.maps.Marker( {
					'position':	new google.maps.LatLng( latitude, longitude ),
					'map':		map,
					'icon':		icon,
					'title':	title,
					'zIndex':	zIndex
				} );
				
				google.maps.event.addListener( marker, 'click', function()
				{
					if( pgm.previousInfoWindow != undefined)
						pgm.previousInfoWindow.close();
					
					infowindow.open(map, marker);
					pgm.previousInfoWindow = infowindow;
				} );
				
				
				return true;
			}
			catch( e )
			{
				//$( pgm.canvas ).append( '<p>' + pgm.name + " error: couldn't add map placemarks.</p>");		// add class for making red? other places need this too?	// @todo - need to figure out a good way to alert user that placemarks couldn't be added
				if( window.console )
					console.log('pgm_createMarker: '+ e);
			}
		}
	} // end pgm
	
	// Kick things off...
	$(document).ready( pgm.init );
	
} // end pgm_wrapper()

pgm_wrapper(jQuery);