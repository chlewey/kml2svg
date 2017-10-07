<?php
header('Content-type: image/svg+xml');
define('CONV', 180/pi());
define('HPI', pi()/2);
define('SQ2', sqrt(2) );
define('HSQ2', sqrt(2)/2 );

function wrt($x) { return sprintf('%.3f', 1000*$x); }

function lam_x0($x0,$y0=SQ2) {
	if($x0==0) return 0;
	$x2 = $x0*$x0;
	$y2 = $y0*$y0;
	$xy = $x0*$y0;
	$ob = ($y2+$x2)/$xy;
	$half = atan($x0/$y0);
	return ( $ob*$ob*$half - ($y2-$x2)/$xy )/2;
}

function x0_lam($lambda, $y0=SQ2, $x0=0, $eps = 1e-14, $dd = 1e-3, $n = 40) {
	if($n<=0) return NaN;
	$l = lam_x0($x0,$y0);
	$d = $lambda - $l;
	if(abs($d)<$eps) return $x0;
	$dm = $l - lam_x0($x0+$dd,$y0);
	$m = $dm / $dd;
	#echo sprintf("[%02d] %8.5f %8.5f %8.5f %8.5f %10.3e %g\n", $n, $lambda, $x0, $y0, $l, $d, $m);
	return x0_lam($lambda, $y0, $x0-$d/$m, $eps, $dd, --$n);
}

function vars_deg($ln,$lt=0, $y0=SQ2) {
	$lambda = $ln/CONV;
	$phi = $lt/CONV;
	
	if($lambda == 0.0) {
		$x0 = 0.0;
		$alpha = 0.0;
		$r = INF;

		$x = 0.0;
		/**/$y = $y0*$phi/HPI;
	} else {
		$x0 = x0_lam($lambda, $y0);
		list($r, $alpha) = vars_x0($x0, $y0);

		/**/$beta = $phi*$alpha/HPI;
		$x = $x0-$r+$r*cos($beta);
		$y = $r*sin($beta);
	}
	
	return [$lambda, $phi, $r, $alpha, $x0, $y0, $x, $y,
		'r'=>$r,
		'alpha'=>$alpha,
		'x0'=>$x0,
		'y0'=>$y0,
		'x'=>$x,
		'y'=>$y
	];
}

function xy_deg($ln,$lt=0, $y0=SQ2) {
	$ar = vars_deg($ln,$lt,$y0);
	return [$ar['x'], $ar['y']];
}

function vars_x0($x0, $y0=SQ2) {
	$alpha = 2*atan($x0/$y0);
	$r = ($x0*$x0+$y0*$y0)/(2*$x0);
	return [$r, $alpha, 'r'=>$r, 'alpha'=>$alpha];
}

function newgroup($svg, $name) {
	$layer = $svg->addChild('g');
	$layer['id'] = $name;
	$layer['transform'] = "matrix(0.25,0,0,-0.25,750,500)";
	return $layer;
}

function PMname($pm) {
	$name = isset($pm->name)? $pm->name: null;
	if(is_null($name)) {
		foreach($pm->ExtendedData as $v=>$w) {
			if((string)$w->Data['name']=='Name')
				$name = $w->Data->value;
		}
	}
	return $name;
}

function PMdraw($g, $pm, $id=null) {
	global $svg;
	if(empty($id)) $id=PMname($pm);

#	$svg = $this->svg;
	if(isset($P->Polygon)) {
		$co = $P->Polygon->outerBoundaryIs->LinearRing->coordinates;
		$st = $P->styleUrl;
		$s = cseries($co);
		if(isset($P->Polygon->innerBoundaryIs)) {
			$px = ["M $s z"];
			foreach($P->Polygon->innerBoundaryIs as $i=>$ib) {
				$co = $ib->LinearRing->coordinates;
				$si = cseries($co);
				$px[] = "M $si z";
			}
			$p = $svg->newpath(implode(' ',$px),$id,$st);
			$p->addAttribute('style','fill-rule:evenodd');
		} else {
			$p = $svg->newpoly($s,$id,$st);
		}
		$this->setstyle($p, $st);
	} elseif(isset($P->Point)) {
		$co = $P->Point->coordinates;
		$c = explode(',',$co);
		list($x,$y) = $this->shftrnd2($this->txcoord($c[0],$c[1]));
		$st = $P->styleUrl;
		$p = $svg->newpoint($x,$y,3,$id,$st);
		$this->setstyle($p, $st);
	} elseif(isset($P->LineString)) {
		$co = $P->LineString->coordinates;
		$st = $P->styleUrl;
		$p = $svg->newpath('M'.$this->cseries($co),$id,$st);
		$this->setstyle($p, $st);
	} elseif(isset($P->MultiGeometry)) {
		$st = $P->styleUrl;
		$d = '';
		foreach($P->MultiGeometry->Polygon as $i=>$mg) {
			$co = $mg->outerBoundaryIs->LinearRing->coordinates;
			$d.= 'M '.$this->cseries($co).' z';
			if(isset($P->MultiGeometry->Polygon->innerBoundaryIs)) {
				foreach($P->MultiGeometry->Polygon->innerBoundaryIs as $i=>$ib) {
					$co = $ib->LinearRing->coordinates;
					$d.= 'M '.$this->cseries($co).' z';
				}
			} else {
				$p = $svg->newpoly($s,$id,$st);
			}
		}
		$p = $svg->newpath($d,$id,$st);
		$p->addAttribute('style','fill-rule:evenodd');
		$this->setstyle($p, $st);/**/
	} else {
		echo "<strong> $name </strong><br/>\n";
	}
}

$svg = simplexml_load_string('<svg xmlns="http://www.w3.org/2000/svg"/>');
$svg['width'] = 1500;
$svg['height'] = 1000;

$defs = $svg->addChild('defs');
$defs->style = <<<style
text{font-family:sans-serif;font-size:12px}
.ar{text-align:end;text-anchor:end}
.ac{text-align:center;text-anchor:middle}
.rad{font-family:serif;font-size:16px}
.line{fill:none;stroke:black;stroke-width:2px}
style;

$layer = newgroup($svg, 'axes');

$ar = vars_deg(180);
$rr = $ar['r'];
$bb = $ar['x0'];

$circ = $layer->addChild('circle');
$circ['cx'] = wrt($rr-$bb);
$circ['cy'] = 0;
$circ['r'] = wrt($rr);
$circ['fill'] = "#59b";

$circ = $layer->addChild('circle');
$circ['cx'] = wrt($bb-$rr);
$circ['cy'] = 0;
$circ['r'] = wrt($rr);
$circ['fill'] = "#59b";

$circ = $layer->addChild('circle');
$circ['cx'] = 0;
$circ['cy'] = 0;
$circ['r'] = $sq2 = wrt(SQ2);
$circ['fill'] = "#39c";

for($i = -180; $i<=180; $i+=15) {
	$p = $layer->addChild('path');
	$p['id'] = $i<0? -$i.'_W': ($i>0? $i.'_E': 'GMT');
	$p['class'] = 'line';
	if($i==0) {
		$p['d'] = 'M0,'.wrt(SQ2).'V'.wrt(-SQ2);
		continue;
	}
	list($lm,$ph,$r,$al,$x0,$y0) = vars_deg($i);
	$rs = wrt($r);
	$k = (int)($x0<0);
	$p['d'] = sprintf('M0,%sA%s,%s 0 0 %d %s,0 %s,%s 0 0 %d 0,-%s',$sq2,$rs,$rs,$k,wrt($x0),$rs,$rs,$k,$sq2);
}

for($j = -75; $j<90; $j+=15) {
	$p = $layer->addChild('path');
	$p['id'] = $j<0? -$j.'_S': ($j>0? $j.'_N': 'EQ');
	$p['class'] = 'line';
	$a = [];
	for($i = -180; $i<=180; $i+=5) {
		list($lm,$ph,$r,$al,$x0,$y0,$x,$y) = vars_deg($i,$j);
		$a[] = wrt($x).','.wrt($y);
	}
	$p['d'] = 'M'.implode(' ',$a);
}


$kml = simplexml_load_file('World_Country_Borders_KML.kml');
$doc = $kml->Document;

if(isset($doc->Placemark))
	$layer = newgroup($svg, 'map');
	foreach($doc->Placemark as $P)
		PMdraw($layer, $P);
	
if(isset($doc->Folder))
	foreach($doc->Folder as $k=>$v) {
		$layer = newgroup($svg, $v->name);
		foreach($v->Placemark as $P)
			PMdraw($layer, $P);
	}


$dom = dom_import_simplexml($svg)->ownerDocument;
$dom->formatOutput = true;
$xml_text = $dom->saveXML($dom->documentElement);
$xml_text = preg_replace('/  /',"\t",$xml_text);

?><?xml version="1.0" encoding="UTF-8" standalone="no"?>

<?=$xml_text?>
<!--
<?php
/*
echo "Hola Mundo!\n";
$x0 = 0;
$lm = 0;
for($i=0; $i<=100; $i++) {
	$x = -2+0.04*$i;
	$lm = $x*pi();
	$x0 = x0_lam($lm);
	echo sprintf("%3d) %5.2f %9.6f %9.6f\n", $i, $x, $x0, $lm);
}*/

#var_dump($ar);
?>
-->
