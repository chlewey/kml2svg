<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

$R = isset($_GET['r'])? $_GET['r']: 500;
$a = deg2rad(isset($_GET['lat'])?$_GET['lat']:40);
$Ca = cos($a);
$Sa = sin($a);
$b = deg2rad(isset($_GET['lon'])?$_GET['lon']:-100);
$Cb = cos($b);
$Sb = sin($b);

function LambEqArea($lon,$lat) {
	global $Ca,$Sa,$b;
	
	$phi = deg2rad($lon)-$b;
	$the = deg2rad($lat);
	$x = sin($phi)*cos($the);
	$y = sin($the);
	$z = cos($phi)*cos($the);
	$y2 = $y*$Ca-$z*$Sa;
	$z2 = 1-($y*$Sa+$z*$Ca);
	$r = sqrt($x*$x+$y2*$y2+$z2*$z2);
	$r0 = sqrt($x*$x+$y2*$y2);
	$x3 = $r0==0? $r: $r*$x/$r0;
	$y3 = $r0==0? $r: $r*$y2/$r0;
	#return [$x, $y2];
	return [$x3/2, $y3/2];
}

function txcoord($lon,$lat) {
	$projection = isset($_GET['proj'])? $_GET['proj']: 'laea';
	switch($projection) {
	default:
		return LambEqArea($lon,$lat);
	}
}

$R = isset($_GET['r'])? $_GET['r']: 500;
function cseries($str) {
	global $R,$name;
	#echo "<b>$name:</b> (";
	$cpairs = explode(' ',$str);
	#echo count($cpairs)." points) <br>\n";
	$slon = $slat = null;
	$sx = $sy = null;
	$d = [];
	foreach($cpairs as $cp) {
		list($lon,$lat,$h) = explode(',',$cp);
		list($x,$y) = txcoord($lon,$lat);
		if(is_null($slat)) {
			#echo 'm ';
			$d[] = sprintf("%.2f,%.2f",$R*(1+$x),$R*(1-$y));
		} else {
			#echo 'l ';
			mkline($d,$slon,$slat,$lon,$lat,0.01);
		}
		$slat=$lat;
		$slon=$lon;
	}
	return implode(' ',$d);
}

function mkline(&$d,$ln0,$lt0,$ln1,$lt1,$off=0.1,$x0=null,$y0=null) {
	global $R,$name;
	if($ln0>90 && $ln1<-90)
		return mkline($d,$ln0,$lt0,$ln1+360,$lt1,$off,$x0,$y0);
	if($ln0<-90 && $ln1>90)
		return mkline($d,$ln0+360,$lt0,$ln1,$lt1,$off,$x0,$y0);
	if(is_null($y0))
		list($x0,$y0) = txcoord($ln0,$lt0);
	list($x,$y) = txcoord($ln1,$lt1);
	$i = 1.0;
	while($i>0.001) {
		$ln = $i*$ln1+(1-$i)*$ln0;
		$lt = $i*$lt1+(1-$i)*$lt0;
		list($x,$y) = txcoord($ln,$lt);
		if(abs($x-$x0)<$off && abs($y-$y0)<$off)
			break;
		$i*=0.63;
	}
	$d[] = sprintf("%.2f,%.2f",$R*(1+$x),$R*(1-$y));
	if($i<1.0) {
		mkline($d,$ln,$lt,$ln1,$lt1,$off,$x,$y);
	}
	#echo "$i ($ln0,$lt0) ($ln,$lt) ($ln1,$lt1) <br>\n";
}

$kmlfile = isset($_GET['file'])? $_GET['file']: 'default.kml';
#TODO: recognize if it is a KMZ file and decompress it.
$X = simplexml_load_file($kmlfile);

$o = new SimpleXMLElement('<svg xmlns="http://www.w3.org/2000/svg" version="1.1" />',LIBXML_NOENT);
$o->addAttribute('height',2*$R);
$o->addAttribute('width',2*$R);
$o->addAttribute('viewBox',sprintf("0 0 %d %d",2*$R,2*$R));

$o->addChild('desc',$X->Document->name);


$layer = $o->addChild('g');
$C=$layer->addChild('circle');
$C->addAttribute('cx',$R);
$C->addAttribute('cy',$R);
$C->addAttribute('r',$R);
$C->addAttribute('fill','#9bc');
$C->addAttribute('id','globe');
$p = $layer->addChild('path');
$p->addAttribute('d',sprintf("m %d,%d 0,%d m %d,%d %d,0",$R,0.97*$R,0.06*$R,-0.03*$R,-0.03*$R,0.06*$R));
$p->addAttribute('style','fill:none;stroke:black;opacity:0.25');
$p->addAttribute('id','croshairs');
for($i=-75;$i<=75;$i+=15) {
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("-180,$i,0 -90,$i,0 0,$i,0 90,$i,0 180,$i,0"));
	$p->addAttribute('style','fill:none;stroke:white;opacity:0.25');
	$p->addAttribute('id','par-'.abs($i).($i<0?'S':($i>0?'N':'')));
}
for($i=-180;$i<180;$i+=15) {
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("$i,89,0 $i,0,0 $i,-89,0"));
	$p->addAttribute('style','fill:none;stroke:white;opacity:0.25');
	$p->addAttribute('id','mer-'.abs($i).($i<0?'W':($i>0?'E':'')));
}
foreach(['TCan'=>23.5,'TCap'=>-23.5,'Art-PC'=>66.5,'Ant-PC'=>-66.5] as $n=>$i) {
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("-180,$i,0 -90,$i,0 0,$i,0 90,$i,0 180,$i,0"));
	$p->addAttribute('style','fill:none;stroke:#578;stroke-dasharray:3,2,3,5');
	$p->addAttribute('id',$n);
}

foreach($X->Document->Folder as $k=>$v) {
	$layer = $o->addChild('g');
	$layer->addAttribute('id',$v->name);
	$pm = $v->Placemark;
	foreach($pm as $m) {
		$name = $m->name;
		if(isset($m->Polygon)) {
			$co = $m->Polygon->outerBoundaryIs->LinearRing->coordinates;
			/*$cc = explode(' ',$co);
			$pt = [];
			foreach($cc as $cu) {
				$c = explode(',',$cu);
				list($x,$y) = txcoord($c[0],$c[1]);
				$pt[] = sprintf("%.2f,%.2f",$R*(1+$x),$R*(1-$y));
			}*/
			$st = '#'.substr($m->styleUrl,6,6);
			
			$p = $layer->addChild('polygon');
			$p->addAttribute('points',cseries($co));
			$p->addAttribute('style',"fill:$st;fill-opacity:.5;stroke:$st");
			$p->addAttribute('id',$name);
		} elseif(isset($m->Point)) {
			$co = $m->Point->coordinates;
			$c = explode(',',$co);
			list($x,$y) = txcoord($c[0],$c[1]);

			$p = $layer->addChild('circle');
			$p->addAttribute('cx', $R*(1+$x));
			$p->addAttribute('cy', $R*(1-$y));
			$p->addAttribute('r', 3);
			$p->addAttribute('style', 'opacity:.5;fill:white;stroke:black');
			$p->addAttribute('id',$name);
		} elseif(isset($m->LineString)) {
			$co = $m->LineString->coordinates;
			$cc = explode(' ',$co);
			if(count($cc)<=2) continue;
			$st = '#'.substr($m->styleUrl,6,6);
			$p = $layer->addChild('path');
			$p->addAttribute('d','M'.cseries($co));
			$p->addAttribute('style',"fill:none;stroke:$st");
			$p->addAttribute('id',$name);
		}
	}
}

// Mora: 4.680.000 (dos facturas), +octubre: total: 9.172.000; 9.460.000 (con mora); 
header('content-type: image/svg+xml; charset=utf8');
echo $o->asXML();
#echo "<!--"; print_r($X); echo "-->";
?>

