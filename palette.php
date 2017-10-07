<?php
header('Content-type: text/plain; charset=utf-8');

function getstyle($kml, $id) {
	foreach($kml->Style as $style) {
		if($id == (string)$style['id'])
			return($style);
		#print_r($style);
	}
	return '/* not found */';
}

class style {
	function __construct($style, $kml) {
		if(is_string($style)) {
			global $styles;
			$style = $styles[$style];
		}
		$this->id = (string)$style['id'];
		$this->color = null;
		if(isset($style->LineStyle)) {
			$this->line = new stdClass;
			$color = (string)$style->LineStyle->color;
			preg_match('{(\w\w)(\w\w)(\w\w)(\w\w)}',$color,$cm);
			$this->color = $this->line->color = [hexdec($cm[4]),hexdec($cm[3]),hexdec($cm[2])];
			$this->line->opacity = hexdec($cm[1]) / 255.0;
			$this->line->width = (float)$style->LineStyle->width;
		}
		if(isset($style->PolyStyle)) {
			$this->poly = new stdClass;
			$color = (string)$style->PolyStyle->color;
			preg_match('{(\w\w)(\w\w)(\w\w)(\w\w)}',$color,$cm);
			#print_r($cm);
			$this->color = $this->poly->color = [hexdec($cm[4]),hexdec($cm[3]),hexdec($cm[2])];
			$this->poly->opacity = hexdec($cm[1]) / 255.0;
			$this->poly->fill = (float)$style->PolyStyle->fill;
			$this->poly->outline = (float)$style->PolyStyle->outline;
		}
		if(isset($style->BalloonStyle)) $this->balloon = $style->BalloonStyle;
	}
}

class stylemap {
	function __construct($map, $kml) {
		global $styles;
		$this->id = (string)$map['id'];
		$this->color = null;
		foreach($map->Pair as $pair) {
			$key = (string)$pair->key;
			if(isset($pair->styleUrl)) {
				$styleUrl = (string)$pair->styleUrl;
				if(substr($styleUrl,0,1)=='#') {
					$this->$key = new style(substr($styleUrl,1),$kml);
					$this->color = $this->$key->color;
				}
				else 
					$this->$key = $styleUrl;
			}
			else $this->$key = $pair;
			#echo "$key\n";
		}
		#$this->map = $map;
	}
}

$file = empty($_GET['file'])? 'Great Mali.kml': $_GET['file'];
$kml = simplexml_load_file($file);
$pal = [];
$styles = [];

foreach ( $kml->Document->Style as $style ) {
	$id = (string)$style['id'];
	$styles[$id] = $style;
	#echo "$id\n";
}
#print_r($styles);

foreach ( $kml->Document->StyleMap as $map ) {
	$id = (string)$map['id'];
	$pal[$id] = new stylemap($map, $kml);
	#echo "$id\n";
}

$abc = explode("\n",<<<ABC
map-gray

people-khoisan
people-bantu
people-georgian

people-berbers
people-turkish
people-celts

people-forests
people-magyars
people-finnic

people-finnogerm
people-german
people-anglos

people-mali
people-bulgars
people-slavs

people-baltic
people-avars
people-mongols

map-byzantium 8 9 10
map-denmark 9 10
map-slovenia 10*
map-georgia 9 10
map-iberia 8 9 10
map-breton 10*
map-saxony 9 10

map-sirte 8 9 10
map-scotland 9 10
map-prussia 9 10
map-eire 10*
map-arabia 8 9 10
map-francia-w 9 10
map-wales 9 10

map-khazar 8 9 10
map-lithuania 9 10
map-yemen *
map-magyar 9 10
map-italia 8 9
map-estonia 9 10
map-bavaria 9 10

map-persia 8 9 10
map-sweden 9 10
map-aquitaine 9 10
map-jerusalem *
map-francia 8 9 10
map-kiev 9 10
map-angland 9 10

map-mali 8 9 10
map-lombardy 9 10
map-armenia 9 10
map-bulgaria 9 10
map-ethiopia 8 9 10
map-moravia 9 10
map-papal 10*

map-tangier 8 9
map-tuscany 10*
map-normandy 10*
map-poland 9 10
map-vasconia 8 9 10
map-sudan *
map-helvetia 10*
ABC
);

$cols = [];
for($i=0;$i<42;$i++) {
	$a = (int)($i / 7);
	$b = $i % 7;
	$o = $a % 2;
	$x = $o? 238-$b*34: $b*34;
	switch($a) {
	case 0:
		$cols[$i] = [238,$x,0]; break;
	case 1:
		$cols[$i] = [$x,238,0]; break;
	case 2:
		$cols[$i] = [0,238,$x]; break;
	case 3:
		$cols[$i] = [0,$x,238]; break;
	case 4:
		$cols[$i] = [$x,0,238]; break;
	case 5:
		$cols[$i] = [238,0,$x]; break;
	}
}

$semi = [];
for($i=0;$i<18;$i++) {
	$a = (int)($i / 3);
	$b = $i % 3;
	$o = $a % 2;
	$x = $o? 204-$b*34: 102+$b*34;
	switch($a) {
	case 0:
		$semi[$i] = [204,$x,102]; break;
	case 1:
		$semi[$i] = [$x,204,102]; break;
	case 2:
		$semi[$i] = [102,204,$x]; break;
	case 3:
		$semi[$i] = [102,$x,204]; break;
	case 4:
		$semi[$i] = [$x,102,204]; break;
	case 5:
		$semi[$i] = [204,102,$x]; break;
	}
}

$xml = simplexml_load_string('<klm><Document/></klm>');
ksort($pal);
$i=0;
$j=0;
foreach($abc as $idx) {
	$idy = explode(' ',$idx);
	$id = $idy[0];
	if(substr($id,0,4)=='map-') {
		if($id=='map-gray') {
			$s0 = $xml->Document->addChild('Style');
			$s1 = $xml->Document->addChild('Style');
			$m = $xml->Document->addChild('StyleMap');
			$s0['id'] = $idn = $id.'-normal';
			$s1['id'] = $idh = $id.'-highlight';
			$m['id'] = $id;
			$p0 = $m->addChild('Pair');
			$p0->addChild('key','normal');
			$p0->addChild('styleUrl',"#$idn");
			$p1 = $m->addChild('Pair');
			$p1->addChild('key','highlight');
			$p1->addChild('styleUrl',"#$idh");
			$sl0 = $s0->addChild('LineStyle');
			$sp0 = $s0->addChild('PolyStyle');
			$sb0 = $s0->addChild('BalloonStyle');
			$sl1 = $s1->addChild('LineStyle');
			$sp1 = $s1->addChild('PolyStyle');
			$sb1 = $s1->addChild('BalloonStyle');
			$sl0->addChild('color','cccccccc');
			$sp0->addChild('color','44cccccc');
			$sl1->addChild('color','cccccccc');
			$sp1->addChild('color','99cccccc');
			$sl0->addChild('width',1);
			$sl1->addChild('width',2);
			$sp0->addChild('fill',1);
			$sp1->addChild('fill',1);
			$sp0->addChild('outline',1);
			$sp1->addChild('outline',1);
		} elseif($id=='map-galitia') {
		} else {
			$c = $cols[$i];
			++$i;
			$s0 = $xml->Document->addChild('Style');
			$s1 = $xml->Document->addChild('Style');
			$m = $xml->Document->addChild('StyleMap');
			$s0['id'] = $idn = $id.'-normal';
			$s1['id'] = $idh = $id.'-highlight';
			$m['id'] = $id;
			$p0 = $m->addChild('Pair');
			$p0->addChild('key','normal');
			$p0->addChild('styleUrl',"#$idn");
			$p1 = $m->addChild('Pair');
			$p1->addChild('key','highlight');
			$p1->addChild('styleUrl',"#$idh");
			$sl0 = $s0->addChild('LineStyle');
			$sp0 = $s0->addChild('PolyStyle');
			$sb0 = $s0->addChild('BalloonStyle');
			$sl1 = $s1->addChild('LineStyle');
			$sp1 = $s1->addChild('PolyStyle');
			$sb1 = $s1->addChild('BalloonStyle');
			$sl0->addChild('color',sprintf('ff%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sp0->addChild('color',sprintf('66%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sl1->addChild('color',sprintf('ff%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sp1->addChild('color',sprintf('99%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sl0->addChild('width',1);
			$sl1->addChild('width',2);
			$sp0->addChild('fill',1);
			$sp1->addChild('fill',1);
			$sp0->addChild('outline',1);
			$sp1->addChild('outline',1);
		}
	} elseif(substr($id,0,7)=='people-') {
			$c = $semi[$j];
			++$j;
			$s0 = $xml->Document->addChild('Style');
			$s1 = $xml->Document->addChild('Style');
			$m = $xml->Document->addChild('StyleMap');
			$s0['id'] = $idn = $id.'-normal';
			$s1['id'] = $idh = $id.'-highlight';
			$m['id'] = $id;
			$p0 = $m->addChild('Pair');
			$p0->addChild('key','normal');
			$p0->addChild('styleUrl',"#$idn");
			$p1 = $m->addChild('Pair');
			$p1->addChild('key','highlight');
			$p1->addChild('styleUrl',"#$idh");
			$sl0 = $s0->addChild('LineStyle');
			$sp0 = $s0->addChild('PolyStyle');
			$sb0 = $s0->addChild('BalloonStyle');
			$sl1 = $s1->addChild('LineStyle');
			$sp1 = $s1->addChild('PolyStyle');
			$sb1 = $s1->addChild('BalloonStyle');
			$sl0->addChild('color',sprintf('ff%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sp0->addChild('color',sprintf('44%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sl1->addChild('color',sprintf('ff%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sp1->addChild('color',sprintf('88%02x%02x%02x',$c[2],$c[1],$c[0]));
			$sl0->addChild('width',1);
			$sl1->addChild('width',2);
			$sp0->addChild('fill',1);
			$sp1->addChild('fill',1);
			$sp0->addChild('outline',1);
			$sp1->addChild('outline',1);
	}
}
#echo $xml->asXML();
$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
echo $dom->saveXML();

#foreach($pal as $id=>$map)
#	echo "$id\n";
?>
