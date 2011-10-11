<p>Enter the address of the placemark. You can type in anything that you would type into a Google Maps search field, from a full address to an intersection, landmark, city or just a zip code.</p>

<table id="pgm-placemark-coordinates">
    <tbody>
        <tr>
            <th><label for="<?php echo self::PREFIX; ?>address">Address:</label></th>
            <td><input id="<?php echo self::PREFIX; ?>address" name="<?php echo self::PREFIX; ?>address" type="text" value="<?php echo $address; ?>" style="width: 200px;" /></td>
        </tr>
	<tr>
            <th><label for="<?php echo self::PREFIX; ?>latitude">Latitude:</label></th>
            <td><input id="<?php echo self::PREFIX; ?>latitude" name="<?php echo self::PREFIX; ?>latitude" type="text" value="<?php echo $latitude; ?>" readonly="readonly" /></td>
        </tr>
	<tr>
            <th><label for="<?php echo self::PREFIX; ?>longitude">Longitude:</label></th>
            <td><input id="<?php echo self::PREFIX; ?>longitude" name="<?php echo self::PREFIX; ?>longitude" type="text" value="<?php echo $longitude; ?>" readonly="readonly" /></td>
        </tr>
    </tbody>
</table>