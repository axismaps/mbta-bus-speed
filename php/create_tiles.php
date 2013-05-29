<?php

ini_set("memory_limit", "800M");
set_time_limit(300);

if (isset($_GET['since'])){
	$since = $_GET['since'];
} else {
	// default to 24 hours if not specified
	$since = 86400;
}
$time = time() - $since;

// to save tiles in a subdirectory
if (isset($_GET['path'])){
	$path = $_GET['path']."/";
} else {
	$path = "bus/";
}

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

// used to adjust transparency; if showing less time, make lines a little more opaque
$alpha = 115 - ( 25-(25*($since/3600)/24) );

$minZ = 11;
$minX = 618;
$minY = 755;
$maxZ = 14;
$zoom = $minZ;

$columns = 3;
$rows = 5;

while ( $zoom <= $maxZ ){
	$width = $columns  * 256;
	$height = $rows * 256;
	$im = imagecreatetruecolor($width, $height) or die("Cannot Initialize new GD image stream");
	$black = imagecolorallocatealpha($im,254,254,254,127); 
   	imagefill($im,0,0,$black); 
	imagesavealpha($im, true);
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
			imageline ($im, xtile($vehicle[$i-1][1],$zoom), ytile($vehicle[$i-1][0],$zoom), xtile($vehicle[$i][1],$zoom),ytile($vehicle[$i][0],$zoom), $color);
		}
	}
	for ( $x = 0; $x < $columns; $x++ ){
		for ( $y = 0; $y < $rows; $y++ ){
			$tile = imagecreatetruecolor(256, 256);
			$b = imagecolorallocatealpha($tile,255,255,255,127);
			imagefill($tile,0,0,$b); 
			imagesavealpha($tile, true);
			imagecopy( $tile, $im, 0, 0, 256*$x, 256*$y, 256, 256 );
			imagepng( $tile, "images/tiles/".$path.$zoom."-".($x+$minX)."-".($y+$minY).".png" );
			imagedestroy($tile);
		}
		
	}
	imagedestroy($im);
	$zoom++;
	$minX *= 2;
	$minY *= 2;
	$columns *= 2;
	$rows *= 2;
}

function xtile( $lon, $zoom ){
	global $minX;
	return ( (($lon + 180) / 360) * pow(2, $zoom) - $minX ) * 256;
}

function ytile( $lat, $zoom ){
	global $minY;
	return ( ( (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) /2 * pow(2, $zoom) ) - $minY ) * 256;
}

?>