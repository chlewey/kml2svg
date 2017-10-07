<?php
header('Content-type: image/svg+xml');
define('CONV', 1440/pi());
define('HPI', pi()/2);

$svg = simplexml_load_string('<svg xmlns="http://www.w3.org/2000/svg"/>');
$svg['width'] = 800;
$svg['height'] = 800;

$defs = $svg->addChild('defs');
$defs->style = <<<style
text{font-family:sans-serif;font-size:12px}
.ar{text-align:end;text-anchor:end}
.ac{text-align:center;text-anchor:middle}
.rad{font-family:serif;font-size:16px}
style;

$layer = $svg->addChild('g');
$layer['id'] = 'axes';
$layer['transform'] = "matrix(1,0,0,-1,60,750)";

$p = $layer->addChild('path');
$p['style'] = 'stroke:#966;fill:none';
$p['d'] = sprintf('M0 %.2fH720M%.2f 0V720', CONV, CONV);

$p = $layer->addChild('path');
$p['style'] = 'stroke:#999;fill:none';
$d = '';
for($i=720;$i;$i-=40) { $d.="M0 {$i}H720"; }
for($i=40;$i<=720;$i+=40) { $d.="M{$i} 0V720"; }
$p['d'] = $d;

$p = $layer->addChild('path');
$p['style'] = 'stroke:black;fill:none';
$p['d'] = 'M0 720V0H720';

$labrad = ['0', '', '⅙π', '¼π', '⅓π', '', '½π'];
$labels = $layer->addChild('g');
$labels['id'] = 'labels';
$labels['transform'] = 'scale(1,-1)';
for($i=0; $i<=6; $i++) {
	$t = $labels->addChild('text');
	$t['x'] = 120*$i;
	$t['y'] = 40;
	$t['class'] = "rad ac";
	$t->tspan = $labrad[$i];

	$t = $labels->addChild('text');
	$t['x'] = -30;
	$t['y'] = 9-120*$i;
	$t['class'] = "rad ar";
	$t->tspan = $labrad[$i];
}
for($i=0; $i<=6; $i++) {
	$t = $labels->addChild('text');
	$t['x'] = 120*$i;
	$t['y'] = 20;
	$t['class'] = "ac";
	$t->tspan = (15*$i).'°';

	$t = $labels->addChild('text');
	$t['x'] = -2;
	$t['y'] = 4-120*$i;
	$t['class'] = "ar";
	$t->tspan = (15*$i).'°';
}

function scurve($x) {
	return $x+cos($x)*sin($x);
}

function ascurve($y,$a=0,$b=HPI,$pre=0.0001) {
	$d = scurve($a)-$y;
	if(abs($d)<$pre) return $a;
	$b/=2;
	if($d>0)
		$a-=$b;
	else
		$a+=$b;
	return ascurve($y,$a,$b);
}

$layer = $svg->addChild('g');
$layer['id'] = 'graph';
$layer['transform'] = "matrix(1,0,0,-1,60,750)";

$id = $layer->addChild('path');
$id['style'] = "fill:none;stroke:red";
$id['d'] = "M0 0L720 720";

$sf = $layer->addChild('path');
$sf['style'] = "fill:none;stroke:green";
$d = "M";
for($i=0;$i<=720;$i++) {
	$rad = $i/CONV;
	$y = sin($rad)*HPI;
	$d.= sprintf(' %d %.2f', $i, CONV*$y);
}
$sf['d'] = $d;

$ss = $layer->addChild('path');
$ss['style'] = "fill:none;stroke:blue";
$d = "M";
for($i=0;$i<=720;$i++) {
	$rad = $i/CONV;
	#$y = $rad + sin($rad)*cos($rad);
	$y = scurve($rad);
	$d.= sprintf(' %d %.2f', $i, CONV*$y);
}
$ss['d'] = $d;

$si = $layer->addChild('path');
$si['style'] = "fill:none;stroke:#c90";
$d = "M";
for($i=0;$i<=720;$i++) {
	$rad = $i/CONV;
	$y = $rad + sin($rad)*cos($rad);
	$y = asin($y/HPI);
	$d.= sprintf(' %.2f %d', CONV*$y, $i);
}
$si['d'] = $d;

$sj = $layer->addChild('path');
$sj['style'] = "fill:none;stroke:#9c0";
$d = "M";
for($i=0;$i<=720;$i++) {
	$rad = $i/CONV;
	$y = sin(ascurve($rad))*HPI;
	$d.= sprintf(' %d %.2f', $i, CONV*$y);
}
$sj['d'] = $d;

$sc = $layer->addChild('path');
$sc['style'] = "fill:none;stroke:#c09";
$d = "M";
for($i=0;$i<=720;$i++) {
	$y = sqrt(1440*$i-$i*$i);
	$d.= sprintf(' %d %.2f', $i, $y);
}
$sc['d'] = $d;

$dom = dom_import_simplexml($svg)->ownerDocument;
$dom->formatOutput = true;
$xml_text = $dom->saveXML($dom->documentElement);
$xml_text = preg_replace('/  /',"\t",$xml_text);

?><?xml version="1.0" encoding="UTF-8" standalone="no"?>

<?=$xml_text?>
