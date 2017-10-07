<?php
header('Content-type: text/plain; charset=utf-8');

$def_output = 'distances.kml';

$lat = 4.6983;
$lon = -74.1408;
$slt = sin(deg2rad($lat));
$clt = cos(deg2rad($lat));
$sln = sin(deg2rad($lon));
$cln = cos(deg2rad($lon));

$xml = simplexml_load_string('<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>Distances</name></Document></kml>');
$layer = $xml->Document->addChild('Folder');
$layer->name = 'distances';

for($i=1;$i<20;$i++) {
	$deg = $i*9;
	$rad = deg2rad($deg);
	$sin = sin($rad);
	$cos = cos($rad);
	$steps = round(180*$sin);
	$z = $cos;

	$d1 = [];
	$d2 = [];
	for($j=0;$j<=$steps;$j++) {
		$d = 180.0*$j/$steps;
		$r = deg2rad($d);
		$c = cos($r);
		$s = sin($r);
		$x = $s*$sin;
		$y = $c*$sin;
		$y1 = $y*$clt + $z*$slt;
		$z1 = $z*$clt - $y*$slt;
		$x2 = $x*$cln + $z1*$sln;
		$z2 = $z1*$cln - $x*$sln;
		$r = sqrt($x2*$x2+$z2*$z2);
		$th = atan2($x2,$z2);
		$ph = atan2($y1,$r);
		$d1[] = sprintf("%.3f,%.3f", rad2deg($th), rad2deg($ph));
		$x2 = $z1*$sln - $x*$cln;
		$z2 = $z1*$cln + $x*$sln;
		$th = atan2($x2,$z2);
		$d2[] = sprintf("%.3f,%.3f", rad2deg($th), rad2deg($ph));
	}
	$pm = $layer->addChild('Placemark');
	$pm->name = "$i.000km E";
	$pm->LineString->coordinates = implode(' ',$d1);

	$pm = $layer->addChild('Placemark');
	$pm->name = "$i.000km W";
	$pm->LineString->coordinates = implode(' ',$d2);
}

$layer = $xml->Document->addChild('Folder');
$layer->name = 'acimuts';

$aln = $lon>0? $lon-180: $lon+180;
$alt = -$lat;
for($a=0; $a<360; $a+=15) {
	$pm = $layer->addChild('Placemark');
	$pm->name = "{$a}°";

	$r = deg2rad($a);
	$c = cos($r);
	$s = sin($r);
	$y = $c*$clt;
	$zi = -$c*$slt;
	$x = $s*$cln + $zi*$sln;
	$z = $zi*$cln - $s*$sln;
	$r = sqrt($x*$x+$z*$z);
	$th = atan2($x,$z);
	$ph = atan2($y,$r);
	$mln = rad2deg($th);
	$mlt = rad2deg($ph);
	
	$pm->LineString->coordinates = "$lon,$lat $mln,$mlt $aln,$alt";
}

$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
$xml_text = $dom->saveXML($dom->documentElement);
$xml_text = preg_replace('/  /',"\t",$xml_text);
file_put_contents($def_output, "<?xml version='1.0' encoding='UTF-8'?>\n".$xml_text);

header('Content-type: application/vnd.google-earth.kml+xml; charset=utf-8');
header('Content-type: text/xml; charset=utf-8');
echo $xml_text;
?>
