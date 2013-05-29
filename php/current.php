<?php

ini_set("memory_limit", "500M");

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
//$mysqli = new mysqli(  $host, $username, $password, $dbname );
		
$query = "SELECT * FROM bus WHERE time >= $time ORDER BY time";
$result = $mysqli->query( $query );

$trips = array();
$times = array();
while ( $row = $result->fetch_assoc() ){
	$veh = $row['vehicle'];
	if ( !isset($trips[$row['vehicle']]) ){
		$trips[$veh] = array();
		$times[$veh] = $row['time'];

		$trips[$veh] = array( array( floatval($row['lat']), floatval($row['lon']) ) );
	} else if ( $row['time'] != $times[$veh] ){
		$prev = $trips[$veh][ count($trips[$veh]) - 1 ];
		$cur = array( floatval($row['lat']), floatval($row['lon']) ) ;
		$dist = 3959 * acos( cos(deg2rad($prev[0])) * cos(deg2rad($cur[0])) * cos(deg2rad($prev[1]) - deg2rad($cur[1])) + sin(deg2rad($prev[0])) * sin(deg2rad($cur[0])));
		if ( $dist == 0 ) continue;

		$speed = $dist / ( ($row['time'] - $times[$veh])/3600 );
		array_push( $cur, round($speed,2) );
		array_push( $trips[$veh], $cur );

		$times[$veh] = $row['time'];
	}
}

$scale = 9000;
$offsetY = projectLat(42.568695)*$scale;
$offsetX = 71.293781*$scale;

// used in create_image() to adjust transparency; if showing less time, make lines a little more opaque
$alpha = 115 - ( 25-(25*($since/3600)/24) );

create_image();

function create_image(){
	global $trips, $scale, $offsetX, $offsetY, $alpha;
	$im = imagecreatefromjpeg("map_bg2.jpg") or die("Cannot Initialize new GD image stream");
	imagelayereffect($im, IMG_EFFECT_ALPHABLEND);
	$slow = imagecolorallocatealpha($im, 170,0,0,$alpha);
	$med = imagecolorallocatealpha($im, 255,244,50,$alpha);
	$fast = imagecolorallocatealpha($im, 0,240,128,$alpha);
	foreach ( $trips as $vehicle ){
		for ( $i = 1; $i < count( $vehicle ); $i++ ){
			$val = floatval($vehicle[$i][2]);
			if ( $val < 10 ){
				$color = $slow;
			} 
			else if ( $val < 25 ){
				$color = $med;
			} 
			else {
				$color = $fast;
			} 
			imageline ($im,   floatval($vehicle[$i-1][1])*$scale + $offsetX, -(projectLat( floatval($vehicle[$i-1][0]) ) * $scale - $offsetY ), floatval($vehicle[$i][1])*$scale + $offsetX, -(projectLat( floatval($vehicle[$i][0]) ) * $scale - $offsetY ), $color);
		}
	}
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
	header('Content-Type: image/jpeg');
	imagejpeg($im);
	imagedestroy($im);
}

function projectLat( $l ){
	$l = $l * pi() / 180;
	return 180 * log( tan($l) + 1/cos($l) ) / pi();
}
?>