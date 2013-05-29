<?php

/* 

Generates a map of bus speeds over the past 24 hours or specified time period,
and saves it as an image file on the server.

*/

// Memory hog; recommend not running this all the time or anything.
ini_set("memory_limit", "500M");

// 'since' parameter can specify the length of time (in seconds) this map represents, up to 24 hours
if (isset($_GET['since'])){
	$since = $_GET['since'];
} else {
	// default to 24 hours if not specified
	$since = 86400;
}
$time = time() - $since;

/* Connect to the MySQL database where the data are stored.
		This assumes that there is a table named 'bus' with the following fields:
		vehicle (varchar)
		route (varchar)
		lat (float)
		lon (float)
		time (int)
*/
$host = "localhost";
$username = "YOUR_USERNAME";
$password = "YOUR_PASSWORD";
$dbname = "YOUR_DB_NAME";
$mysqli = new mysqli( $host, $username, $password, $dbname );

$query = "SELECT * FROM bus WHERE time >= $time ORDER BY time";
$result = $mysqli->query( $query );

$trips = array();
$times = array();	// to keep track of the timestamp of the most recent point for a vehicle
while ( $row = $result->fetch_assoc() ){
	$veh = $row['vehicle'];
	if ( !isset($trips[$row['vehicle']]) ){
		$trips[$veh] = array();	// create an object for this vehicle if it doesn't exist yet
		$times[$veh] = $row['time'];	// store timestamp for this point

		// vehicle object is an array of arrays, each of which contain lat/lon point as well as speed (for all but the first point)
		$trips[$veh] = array( array( floatval($row['lat']), floatval($row['lon']) ) );
	} else if ( $row['time'] != $times[$veh] ){	// skip if timestamp is the same as the previous point; we don't want duplicates
		$prev = $trips[$veh][ count($trips[$veh]) - 1 ];	// previous point for this vehicle
		$cur = array( floatval($row['lat']), floatval($row['lon']) ) ;	// current point for this vehicle

		// calculate speed between previous and current, in miles per hour
		$dist = 3959 * acos( cos(deg2rad($prev[0])) * cos(deg2rad($cur[0])) * cos(deg2rad($prev[1]) - deg2rad($cur[1])) + sin(deg2rad($prev[0])) * sin(deg2rad($cur[0])));
		if ( $dist == 0 ) continue;
		$speed = $dist / ( ($row['time'] - $times[$veh])/3600 );

		array_push( $cur, round($speed,2) );	// add speed to current point
		array_push( $trips[$veh], $cur );		// add current point to vehicle object

		$times[$veh] = $row['time'];	// store timestamp for this point
	}
}

// variables for sizing and aligning the points (specific to Boston area and map_bg.jpg)
$scale = 9000;
$offsetY = projectLat(42.568695)*$scale;
$offsetX = 71.293781*$scale;

// used in create_image() to adjust transparency; if showing less time, make lines a little more opaque (127 = fully transparent)
$alpha = 115 - ( 25-(25*($since/3600)/24) );

// I think the "archive" directory needs to already exist
$name = "archive/mbta-bus-".date("Y-m-d").".jpg";

create_image();

function create_image(){
	global $trips, $scale, $offsetX, $offsetY, $alpha, $name;
	// create image from map_bg.jpg, which was made in TileMill
	$im = imagecreatefromjpeg("map_bg.jpg") or die("Cannot Initialize new GD image stream");
	imagelayereffect($im, IMG_EFFECT_ALPHABLEND);	// lines are mostly transparent and we want to blend those alphas
	$slow = imagecolorallocatealpha($im, 170,0,0,$alpha);	// red
	$med = imagecolorallocatealpha($im, 255,244,50,$alpha);	// yellow
	$fast = imagecolorallocatealpha($im, 0,240,128,$alpha);	// green
	foreach ( $trips as $vehicle ){
		for ( $i = 1; $i < count( $vehicle ); $i++ ){
			$val = floatval($vehicle[$i][2]);	// speed
			if ( $val < 10 ){
				$color = $slow;
			} 
			else if ( $val < 25 ){
				$color = $med;
			} 
			else {
				$color = $fast;
			}
			// $vehicles[$i][0] is latitude, $vehicles[$i][1] is longitude
			imageline ($im,   $vehicle[$i-1][1]*$scale + $offsetX, -(projectLat( $vehicle[$i-1][0] ) * $scale - $offsetY ), $vehicle[$i][1]*$scale + $offsetX, -(projectLat( $vehicle[$i][0] ) * $scale - $offsetY ), $color);
		}
	}
	// titles, etc. for the Bostonography maps
	$white = imagecolorallocate($im,255,255,255);
	imagettftext($im,50,0,50,100,$white,"pnbold.otf","MBTA Bus Speeds");
	imagettftext($im,20,0,50,150,$white,"pnreg.otf","The speed of buses on ".date("l, F j, Y").",");
	imagettftext($im,20,0,50,190,$white,"pnreg.otf","based on 24 hour of real-time location data.");
	imagettftext($im,20,0,50,250,imagecolorallocate($im, 170,0,0),"pnbold.otf","Red:");
	imagettftext($im,20,0,50,290,imagecolorallocate($im, 255,244,50),"pnbold.otf","Yellow:");
	imagettftext($im,20,0,50,330,imagecolorallocate($im, 0,240,128),"pnbold.otf","Green:");
	imagettftext($im,20,0,150,250,$white,"pnreg.otf","< 10 mph");
	imagettftext($im,20,0,150,290,$white,"pnreg.otf","10 to 25 mph");
	imagettftext($im,20,0,150,330,$white,"pnreg.otf","> 25 mph");
	imagettftext($im,10,0,50,380,$white,"pnreg.otf","Bostonography.com | Street map data copyright OpenStreetMap.org | MBTA bus data via NextBus");

	// Save image twice
	imagejpeg($im,"archive/yesterday.jpg");
	imagejpeg($im,$name);

	// To just output the image instead, comment out the above two lines and use these two
	//header('Content-Type: image/jpeg');
	//imagejpeg($im);

	imagedestroy($im);
}

// Mercator projection
function projectLat( $l ){
	$l = $l * pi() / 180;
	return 180 * log( tan($l) + 1/cos($l) ) / pi();
}
?>