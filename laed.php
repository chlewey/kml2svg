<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

$R = empty($_GET['r'])? 500: $_GET['r'];
$Lt = isset($_GET['lat']) && $_GET['lat']!=''? deg2rad((float)$_GET['lat']): 0.7;
$Clt = cos($Lt);
$Slt = sin($Lt);
$Ln = isset($_GET['lon']) && $_GET['lon']!=''? deg2rad((float)$_GET['lon']): -1.7;
$Cln = cos($Ln);
$Sln = sin($Ln);
$Or = empty($_GET['ori'])? 0: deg2rad((float)$_GET['ori']);
$Cor = cos($Or);
$Sor = sin($Or);
$pi = 2*asin(1);

function LambEqArea($lon,$lat) {
	global $Clt,$Slt,$Ln,$Cor,$Sor,$pi;

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
	$d = 2*asin($r/2);
	$r0 = sqrt($x2*$x2+$y2*$y2);
	$x3 = $r0==0? $d: $d*$x2/$r0;
	$y3 = $r0==0? $d: $d*$y2/$r0;
	#return [$x, $y2];
	return array($x3/$pi, $y3/$pi);
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
	$d = array();
	foreach($cpairs as $cp) {
		if(trim($cp)=="") continue;
		list($lon,$lat/*,$h*/) = explode(',',$cp);
		if(!is_numeric($lon)) { echo "<em>$cp</em> [$name]<br>\n"; continue; }
		list($x,$y) = txcoord($lon,$lat);
		if(is_null($slat)) {
			#echo 'm ';
			#$d[] = sprintf("%.2f,%.2f",$R*(1+$x),$R*(1-$y));
			$d[] = (round($R*(1+$x)*8)/8).','.(round($R*(1-$y)*8)/8);
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
	#$d[] = sprintf("%.2f,%.2f",$R*(1+$x),$R*(1-$y));
	$d[] = (round($R*(1+$x)*8)/8).','.(round($R*(1-$y)*8)/8);
	if($i<1.0) {
		mkline($d,$ln,$lt,$ln1,$lt1,$off,$x,$y);
	}
	#echo "$i ($ln0,$lt0) ($ln,$lt) ($ln1,$lt1) <br>\n";
}

$styles = array();
$ustyles = array();
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

$kmlfile = empty($_GET['file'])? 'default.kml': $_GET['file'];
#TODO: recognize if it is a KMZ file and decompress it.
$X = simplexml_load_file($kmlfile);

$o = new SimpleXMLElement('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" onload="init(evt)"><script id="svgpan" xlink:href="SVGPan.js" /><defs/></svg>',LIBXML_NOENT);
$o->addAttribute('height',2*$R);
$o->addAttribute('width',2*$R);
$o->addAttribute('viewBox',sprintf("0 0 %d %d",2*$R,2*$R));

$o->addChild('desc',$X->Document->name);

function arr2sty(array $ar, $def='') {
	$u=array();
	foreach($ar as $k=>$v)
		$u[] = "$k: $v";
	return implode('; ', $u);
}

$D = $X->Document->children();
$defU = array('opacity'=>'0.75', 'fill'=>'white', 'stroke'=>'black');
foreach($D as $a=>$b) {
	if($a=='Style') {
		$u = array();
		if(isset($b->LineStyle->color)) {
			$s = $b->LineStyle->color;
			preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
			$u['stroke'] = '#'.$m[4].$m[3].$m[2];
			$u['stroke-opacity'] = sprintf('%.3f',hexdec($m[1])/255.0);
			if(!isset($b->PolyStyle))
				$u['fill'] = 'none';
		}
		if(isset($b->LineStyle->width)) {
			$u['stroke-width'] = $b->LineStyle->width;
		}
		if(isset($b->PolyStyle->color)) {
			$s = $b->PolyStyle->color;
			preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
			$u['fill'] = '#'.$m[4].$m[3].$m[2];
			$u['fill-opacity'] = sprintf('%.3f',hexdec($m[1])/255.0);
		}
		if(isset($b->IconStyle->color)) {
			$s = $b->IconStyle->color;
			preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
			$u['fill'] = '#'.$m[4].$m[3].$m[2];
			$u['fill-opacity'] = sprintf('%.3f',hexdec($m[1])/255.0);
			$u['stroke'] = 'black';
			$u['stroke-opacity'] = '0.5';
		}
		$id = (string)$b['id'];
		$styles[$id] = empty($u)? arr2sty($defU): arr2sty($u);
	}
	elseif($a=='StyleMap') {
		$u = array();
		foreach($b->Pair as $v) {
			$key = (string)$v->key;
			$u[$key] = (string)$v->styleUrl;
		}
		$id = (string)$b['id'];
		$styles[$id] = $u;
	}
}/* */

$All = $o->addChild('g');
$All->addAttribute('id','All');
$layer = $All->addChild('g');
$C=$layer->addChild('circle');
$C->addAttribute('cx',$R);
$C->addAttribute('cy',$R);
$C->addAttribute('r',$R);
$glob = empty($_GET['glob'])? '#134': $_GET['glob'];
$C->addAttribute('fill',$glob);
$C->addAttribute('id','globe');
$p = $layer->addChild('path');
$p->addAttribute('d',sprintf("m %d,%d 0,%d m %d,%d %d,0",$R,0.97*$R,0.06*$R,-0.03*$R,-0.03*$R,0.06*$R));
$p->addAttribute('style','fill:none;stroke:black;opacity:0.25');
$p->addAttribute('id','croshairs');
$mer = empty($_GET['lines'])? (empty($_GET['mer'])? 15: (int)$_GET['mer']): (int)$_GET['lines'];
$par = empty($_GET['lines'])? (empty($_GET['par'])? 15: (int)$_GET['par']): (int)$_GET['lines'];
for($i=$par;$i<90;$i+=$par) {
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("-180,$i,0 -90,$i,0 0,$i,0 90,$i,0 180,$i,0"));
	$p->addAttribute('style','fill:none;stroke:white;opacity:0.25');
	$p->addAttribute('id','par-'.abs($i).($i>0?'N':''));
	if($i==0) continue;
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("-180,-$i,0 -90,-$i,0 0,-$i,0 90,-$i,0 180,-$i,0"));
	$p->addAttribute('style','fill:none;stroke:white;opacity:0.25');
	$p->addAttribute('id','par-'.abs($i).'S');
}
for($i=0;$i<=180;$i+=$mer) {
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("$i,89,0 $i,0,0 $i,-89,0"));
	$p->addAttribute('style','fill:none;stroke:white;opacity:0.25');
	$p->addAttribute('id','mer-'.abs($i).($i>0?'E':''));
	if($i==0 || $i==180) continue;
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("-$i,89,0 -$i,0,0 -$i,-89,0"));
	$p->addAttribute('style','fill:none;stroke:white;opacity:0.25');
	$p->addAttribute('id','mer-'.abs($i).'W');
}
foreach(array('Equator'=>0,'TCan'=>23.5,'TCap'=>-23.5,'Art-PC'=>66.5,'Ant-PC'=>-66.5) as $n=>$i) {
	$p = $layer->addChild('path');
	$p->addAttribute('d','M'.cseries("-180,$i,0 -90,$i,0 0,$i,0 90,$i,0 180,$i,0"));
	$p->addAttribute('style','fill:none;stroke:#578;stroke-dasharray:3,2,3,5');
	$p->addAttribute('id',$n);
}

foreach($X->Document->Folder as $k=>$v) {
	$layer = $All->addChild('g');
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
			$g = $layer->addChild('path');
			$g->addAttribute('id',$name);
			#echo "<strong> $name: </strong><br/>\n";
			$st = $m->styleUrl;
			setstyle($g, $st);
			$d = '';
			foreach($m->MultiGeometry->Polygon as $i=>$mg) {
				$co = $mg->outerBoundaryIs->LinearRing->coordinates;
				#$p = $g->addChild('polygon');
				#$p->addAttribute('points',cseries($co));
				$d.= 'M '.cseries($co).' z';
				#$p->addAttribute('id',"$name-$i");
				#echo "$i: ".($mg->outerBoundaryIs->LinearRing->coordinates)."<br/>\n";
			}
			$g->addAttribute('d',$d);
		} else {
			echo "<strong> $name </strong><br/>\n";
		}
	}
}

$pm = $X->Document->Placemark;
foreach($pm as $m) {
	$name = isset($m->name)? $m->name: null;
	if(is_null($name)) {
		foreach($m->ExtendedData as $v=>$w) {
			#print_r([$v,$w,$w->Data,(string)$w->Data['name'],$w->Data->value]);
			if((string)$w->Data['name']=='Name')
				$name = $w->Data->value;
		}
	}
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
		$g = $layer->addChild('path');
		$g->addAttribute('id',$name);
		#echo "<strong> $name: </strong><br/>\n";
		$st = $m->styleUrl;
		setstyle($g, $st);
		$d = '';
		foreach($m->MultiGeometry->Polygon as $i=>$mg) {
			$co = $mg->outerBoundaryIs->LinearRing->coordinates;
			#$p = $g->addChild('polygon');
			#$p->addAttribute('points',cseries($co));
			$d.= 'M '.cseries($co).' z';
			#$p->addAttribute('id',"$name-$i");
			#echo "$i: ".($mg->outerBoundaryIs->LinearRing->coordinates)."<br/>\n";
		}
		$g->addAttribute('d',$d);
	} else {
		echo "<strong> $name </strong><br/>\n";
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

