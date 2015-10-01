<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

$R = isset($_GET['r'])? $_GET['r']: 500;
$Lt = deg2rad(isset($_GET['lat'])?$_GET['lat']:40);
$Clt = cos($Lt);
$Slt = sin($Lt);
$Ln = deg2rad(isset($_GET['lon'])?$_GET['lon']:-100);
$Cln = cos($Ln);
$Sln = sin($Ln);
$Or = deg2rad(isset($_GET['ori'])?$_GET['ori']:0);
$Cor = cos($Or);
$Sor = sin($Or);

function LambEqArea($lon,$lat) {
	global $Clt,$Slt,$Ln,$Cor,$Sor;
	
	$phi = deg2rad($lon)-$Ln;
	$the = deg2rad($lat);
	$x = sin($phi)*cos($the);
	$y = sin($the);
	$z = cos($phi)*cos($the);
	$y1 = $y*$Clt-$z*$Slt;
	$z1 = 1-($y*$Slt+$z*$Clt);
	$x2 = $x*$Cor-$y1*$Sor;
	$y2 = $x*$Sor+$y1*$Cor;
	$r = sqrt($x2*$x2+$y2*$y2+$z1*$z1);
	$r0 = sqrt($x2*$x2+$y2*$y2);
	$x3 = $r0==0? $r: $r*$x2/$r0;
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
		if(empty(trim($cp))) continue;
		list($lon,$lat,$h) = explode(',',$cp);
		if(!is_numeric($lon)) { echo "<em>$cp</em> [$name]<br>\n"; continue; }
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

$styles = [];
$ustyles = [];
function setstyle(&$obj, $desc) {
	global $styles, $ustyles;
	$class = ltrim("$desc",'#');
	#echo ".$class {}\n";
	if(isset($ustyles[$class])) {
		$obj->addAttribute('class',$class);
	} elseif(isset($styles[$class])) {
		if(is_string($s=$styles[$class])) {
			$ustyles[$class] = "{ $s }";
		} else {
			$normal = ltrim( $s['normal'], '#' );
			$highlight = ltrim( $s['highlight'], '#' );
			$ustyles[$class] = '{ '.$styles[$normal].' }';
			$ustyles[$class.':hover'] = '{ '.$styles[$highlight].' }';
		}
		$obj->addAttribute('class',$class);
	} else {
	}
}

$kmlfile = isset($_GET['file'])? $_GET['file']: 'default.kml';
#TODO: recognize if it is a KMZ file and decompress it.
$X = simplexml_load_file($kmlfile);

$o = new SimpleXMLElement('<svg xmlns="http://www.w3.org/2000/svg" version="1.1"><defs/></svg>',LIBXML_NOENT);
$o->addAttribute('height',2*$R);
$o->addAttribute('width',2*$R);
$o->addAttribute('viewBox',sprintf("0 0 %d %d",2*$R,2*$R));

$o->addChild('desc',$X->Document->name);

$D = $X->Document->children();
foreach($D as $a=>$b) {
	if($a=='Style') {
		$u = [];
		if(isset($b->LineStyle->color)) {
			$s = $b->LineStyle->color;
			preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
			$u[] = 'stroke: #'.$m[4].$m[3].$m[2];
			$u[] = sprintf('stroke-opacity: %.3f',hexdec($m[1])/255.0);
		}
		if(isset($b->LineStyle->width)) {
			$u[] = 'stroke-width: '.$b->LineStyle->width;
		}
		if(isset($b->PolyStyle->color)) {
			$s = $b->PolyStyle->color;
			preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
			$u[] = 'fill: #'.$m[4].$m[3].$m[2];
			$u[] = sprintf('fill-opacity: %.3f',hexdec($m[1])/255.0);
		} else {
			$u[] = 'fill: none';
		}
		$id = (string)$b['id'];
		$styles[$id] = implode('; ',$u);
	}
	elseif($a=='StyleMap') {
		$u = [];
		foreach($b->Pair as $v) {
			$key = (string)$v->key;
			$u[$key] = (string)$v->styleUrl;
		}
		$id = (string)$b['id'];
		$styles[$id] = $u;
	}
}/* */

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
foreach(['Equator'=>0,'TCan'=>23.5,'TCap'=>-23.5,'Art-PC'=>66.5,'Ant-PC'=>-66.5] as $n=>$i) {
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
			$st = $m->styleUrl;
			
			$p = $layer->addChild('polygon');
			$p->addAttribute('points',cseries($co));
			setstyle($p, $st);
			$p->addAttribute('id',$name);
		} elseif(isset($m->Point)) {
			$co = $m->Point->coordinates;
			$c = explode(',',$co);
			list($x,$y) = txcoord($c[0],$c[1]);
			$st = $m->styleUrl;

			$p = $layer->addChild('circle');
			$p->addAttribute('cx', $R*(1+$x));
			$p->addAttribute('cy', $R*(1-$y));
			$p->addAttribute('r', 3);
			setstyle($p, $st);
			$p->addAttribute('id',$name);
		} elseif(isset($m->LineString)) {
			$co = $m->LineString->coordinates;
			$cc = explode(' ',$co);
			if(count($cc)<=2) continue;
			$st = $m->styleUrl;
			$p = $layer->addChild('path');
			$p->addAttribute('d','M'.cseries($co));
			setstyle($p, $st);
			$p->addAttribute('id',$name);
		} elseif(isset($m->MultiGeometry)) {
			$g = $layer->addChild('g');
			$g->addAttribute('id',$name);
			#echo "<strong> $name: </strong><br/>\n";
			foreach($m->MultiGeometry->Polygon as $i=>$mg) {
				$co = $mg->outerBoundaryIs->LinearRing->coordinates;
				$st = $m->styleUrl;
				$p = $g->addChild('polygon');
				$p->addAttribute('points',cseries($co));
				setstyle($p, $st);
				$p->addAttribute('id',"$name-$i");
				#echo "$i: ".($mg->outerBoundaryIs->LinearRing->coordinates)."<br/>\n";
			}
		} else {
			echo "<strong> $name </strong><br/>\n";
		}
	}
}

$mstyle = '';
foreach($ustyles as $k=>$v)
	$mstyle.= ".$k $v\n";
if(!isset($o->defs)) {
	$def = $o->addChild('defs');
} else {
	$def = $o->defs;
}
$style = $def->addChild('style',chr(10).$mstyle);

header('content-type: image/svg+xml; charset=utf8');
echo $o->asXML();
#echo "<!--"; print_r($X); echo "-->";
echo "<!--"; print_r($styles); echo "-->";
echo "<!--"; print_r($ustyles); echo "-->";
?>

