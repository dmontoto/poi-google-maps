<?php

function RegisterTaxonomiesDefaultsValues($PoiGoogleMaps) {
    // Creamos los valores por defecto de las regiones
    $spainRegion = $PoiGoogleMaps->insert_term('Spain','region');
    $andaluciaRegion = $PoiGoogleMaps->insert_term('Andalucía','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Almería','region',array('parent'=>$andaluciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Cádiz','region',array('parent'=>$andaluciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Córdoba','region',array('parent'=>$andaluciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Granada','region',array('parent'=>$andaluciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Huelva','region',array('parent'=>$andaluciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Jaén','region',array('parent'=>$andaluciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Málaga','region',array('parent'=>$andaluciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Sevilla','region',array('parent'=>$andaluciaRegion['term_id']));
    $aragonRegion = $PoiGoogleMaps->insert_term('Aragón','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Huesca','region',array('parent'=>$aragonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Teruel','region',array('parent'=>$aragonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Zaragoza','region',array('parent'=>$aragonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Asturias','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Baleares','region',array('parent'=>$spainRegion['term_id']));
    $canariasRegion = $PoiGoogleMaps->insert_term('Canarias','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Las Palmas','region',array('parent'=>$canariasRegion['term_id']));
    $PoiGoogleMaps->insert_term('Santa Cruz','region',array('parent'=>$canariasRegion['term_id']));
    $PoiGoogleMaps->insert_term('Cantabria','region',array('parent'=>$spainRegion['term_id']));
    $castillaManchaRegion = $PoiGoogleMaps->insert_term('Castilla La Mancha','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Albacete','region',array('parent'=>$castillaManchaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Ciudad Real','region',array('parent'=>$castillaManchaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Cuenca','region',array('parent'=>$castillaManchaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Guadalajara','region',array('parent'=>$castillaManchaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Toledo','region',array('parent'=>$castillaManchaRegion['term_id']));
    $castillaLeonRegion = $PoiGoogleMaps->insert_term('Castilla León','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Avila','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Burgos','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('León','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Palencia','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Salamanca','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Segovia','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Soria','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Valladolid','region',array('parent'=>$castillaLeonRegion['term_id']));
    $PoiGoogleMaps->insert_term('Zamora','region',array('parent'=>$castillaLeonRegion['term_id']));
    $catalunyaRegion = $PoiGoogleMaps->insert_term('Cataluña','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Barcelona','region',array('parent'=>$catalunyaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Girona','region',array('parent'=>$catalunyaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Lleida','region',array('parent'=>$catalunyaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Tarragona','region',array('parent'=>$catalunyaRegion['term_id']));
    $comunidadValencianaRegion = $PoiGoogleMaps->insert_term('Comunidad Valenciana','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Alicante','region',array('parent'=>$comunidadValencianaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Castellón','region',array('parent'=>$comunidadValencianaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Valencia','region',array('parent'=>$comunidadValencianaRegion['term_id']));
    $extremaduraRegion = $PoiGoogleMaps->insert_term('Extremadura','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Badajoz','region',array('parent'=>$extremaduraRegion['term_id']));
    $PoiGoogleMaps->insert_term('Cáceres','region',array('parent'=>$extremaduraRegion['term_id']));
    $galiciaRegion = $PoiGoogleMaps->insert_term('Galicia','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('A Coruña','region',array('parent'=>$galiciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Lugo','region',array('parent'=>$galiciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Orense','region',array('parent'=>$galiciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('Pontevedra','region',array('parent'=>$galiciaRegion['term_id']));
    $PoiGoogleMaps->insert_term('La Rioja','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Madrid','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Murcia','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Navarra','region',array('parent'=>$spainRegion['term_id']));
    $paisVascoRegion = $PoiGoogleMaps->insert_term('País Vasco','region',array('parent'=>$spainRegion['term_id']));
    $PoiGoogleMaps->insert_term('Alava','region',array('parent'=>$paisVascoRegion['term_id']));
    $PoiGoogleMaps->insert_term('Guipuzcoa','region',array('parent'=>$paisVascoRegion['term_id']));
    $PoiGoogleMaps->insert_term('Vizcaya','region',array('parent'=>$paisVascoRegion['term_id']));

    $franceRegion = $PoiGoogleMaps->insert_term('France','region');
}
?>
