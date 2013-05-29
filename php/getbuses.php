<?php
	$data = simplexml_load_file("http://webservices.nextbus.com/service/publicXMLFeed?command=vehicleLocations&a=mbta&t=0");
	$time = round($data->lastTime['time']/1000);

	/* Connect to the MySQL database where the data will be stored.
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
	
	foreach($data->vehicle as $veh){
		$query = "INSERT INTO bus (vehicle,route,lat,lon,time) VALUES (".$veh['id'].",".$veh['routeTag'].",".$veh['lat'].",".$veh['lon'].",".($time - $veh['secsSinceReport']).")";
		$result = $mysqli->query( $query );
	}
	$dayAgo = time()-86400;
	$mysqli->query( "DELETE FROM bus WHERE time < $dayAgo" );
?>