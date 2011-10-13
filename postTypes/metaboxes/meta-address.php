<p>Enter the address of the placemark. You can type in anything that you would type into a Google Maps search field, from a full address to an intersection, landmark, city or just a zip code.</p>

<table id="pgm-camping-address" style="width:100%;">
    <tbody>
        <tr>
            <td style="width:50%;">
                <table>
                    <tbody>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>address">Address:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>address" name="<?php echo self::PREFIX; ?>address" type="text" value="<?php echo $address; ?>" style="width: 250px;" /></td>
                        </tr>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>city">City:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>city" name="<?php echo self::PREFIX; ?>city" type="text" value="<?php echo $city; ?>" style="width: 150px;" /></td>
                        </tr>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>postalcode">Postal Code:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>postalcode" name="<?php echo self::PREFIX; ?>postalcode" type="text" value="<?php echo $postalcode; ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>telephone">Telephone:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>telephone" name="<?php echo self::PREFIX; ?>telephone" type="text" value="<?php echo $telephone; ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>fax">Fax:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>fax" name="<?php echo self::PREFIX; ?>fax" type="text" value="<?php echo $fax; ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>email">Email:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>email" name="<?php echo self::PREFIX; ?>email" type="text" value="<?php echo $email; ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>website">Website:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>website" name="<?php echo self::PREFIX; ?>website" type="text" value="<?php echo $website; ?>" style="width: 200px;" /></td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td style="width:50%;">
                <table id="pgm-placemark-coordinates">
                    <tbody>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>latitude">Latitude:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>latitude" name="<?php echo self::PREFIX; ?>latitude" type="text" value="<?php echo $latitude; ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="<?php echo self::PREFIX; ?>longitude">Longitude:</label></th>
                            <td><input id="<?php echo self::PREFIX; ?>longitude" name="<?php echo self::PREFIX; ?>longitude" type="text" value="<?php echo $longitude; ?>" /></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="text-align: center;">
                                <div id="pgmAdmin_map-canvas"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>