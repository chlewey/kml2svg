<?php
header('Content-type: text/plain; charset=utf-8');

$ini = parse_ini_file("fixmap.ini", true);
#print_r($ini);

$def_path = isset($ini['file']['path'])? $ini['file']['path']: '.';
$def_ipath = isset($ini['file']['input_path'])? $ini['file']['input_path']: $def_path;
$def_opath = isset($ini['file']['output_path'])? $ini['file']['output_path']: $def_path;

$def_input = isset($ini['file']['input_file'])? rtrim($def_ipath,'/').'/'.$ini['file']['input_file']: rtrim($def_ipath,'/').'/default.kml';
$def_output = isset($ini['file']['output_file'])? rtrim($def_opath,'/').'/'.$ini['file']['output_file']: rtrim($def_opath,'/').'/fixes.kml';

$config = $ini['config'];


function fix_sing($co) {
	global $config;
	$t = explode(',',$co);
	$t[1] = round($t[1],3);
	$x = round(1000*cos(deg2rad($t[1])));
	$l = round(($t[0]-$config['lon'])*$x);
	$t[0] = round($l/$x+$config['lon'],5);
	$t[2] = 0;
	return implode(',',$t);
}

function fix_coor($coo) {
	$r = explode(' ', $coo);
	for($i=0;$i<count($r);++$i)
		$r[$i] = fix_sing($r[$i]);
	return $r;
}

function load_data($kml, $specials) {
	$process = isset($specials['process'])? $specials['process']: 'process';
	$points = isset($specials['points'])? $specials['points']: 'points';
	$trash = isset($specials['trash'])? $specials['trash']: 'trash';

	$res = new stdClass();

	$res->docname = (string) $kml->Document->name;
	$res->folders = [];
	
	$i=0;
	foreach ( $kml->Document->Folder as $folder ) {
		$pmx = [];
		foreach($folder->Placemark as $pm) {
			$n = (string) $pm->name;
			if(!isset($pmx[$n])) $pmx[$n] = [];
			if(isset($pm->Polygon)) {
				echo "$n -> Polygon\n";
				$coo = (string) $pm->Polygon->outerBoundaryIs->LinearRing->coordinates;
				$x = [fix_coor($coo)];
				foreach($pm->Polygon->innerBoundaryIs as $ib) {
					$coo = (string) $ib->LinearRing->coordinates;
					$x[] = fix_coor($coo);
				}
				$pmx[$n][] = $x;
			} elseif(isset($pm->MultiGeometry)) {
				echo "$n -> MultiGeometry";
				foreach($pm->MultiGeometry->Polygon as $pol) {
					$coo = (string) $pol->outerBoundaryIs->LinearRing->coordinates;
					$x = [fix_coor($coo)];
					foreach($pol->innerBoundaryIs as $ib) {
						$coo = (string) $ib->LinearRing->coordinates;
						$x[] = fix_coor($coo);
					}
					echo ".";
					$pmx[$n][] = $x;
				}
				echo "\n";
			} elseif(isset($pm->LineString)) {
				echo "$n -> Line\n";
				$coo = (string) $pm->LineString->coordinates;
				$x = fix_coor($coo);
				if(empty($pmx[$n])) {
					$c = count($x);
					$ci = $x[0];
					$cf = $x[$c-1];
					
					$a = explode(',',$ci);
					$b = explode(',',$cf);

					$x[] = "{$a[0]},{$b[1]},0";
					$pmx[$n][] = [$x];
				} else {
					$c = count($x);
					$ci = $x[0];
					$cf = $x[$c-1];
					echo "[$ci -- $cf] ($n)\n";
					$f = false;
					for($i=0;$i<count($pmx[$n]);$i++) {
						$o = $pmx[$n][$i][0];
						echo " > ".count($o);
						if(($ii = array_search($ci, $o))!==false) {
							$if = array_search($cf, $o);
							if($if===false) continue;
							if($ii && !$if) {
								$head = array_slice($o,0,-1);
								$pmx[$n][$i][0] = array_merge($head,$x);
								$f = true;
							} elseif($if>$ii) {
								$head = $ii? array_slice($o,0,$ii): [];
								$tail = array_slice($o,$if);
								$pmx[$n][$i][0] = array_merge($head,$x,$tail);
								$f = true;
							} else {
								$tail = array_slice($o,$if,$ii);
								$pmx[$n][$i][0] = array_merge($x,$tail);
								$f = true;
							}
							echo " (done)";
							#break;
						}
					}
					if($f)
						echo "\n";
					else {
						echo "\n##### (NOT)\n";
						if(!isset($pms['Lines'][$n]))
							$pms['Lines'][$n] = ['line'=>[]];
						$pms['Lines'][$n]['line'][] = $x;
					}
				}
			} elseif(isset($pm->Point)) {
				echo "$n -> Point\n";
				$coo = (string) $pm->Point->coordinates;
				$pms['Cities'][$n] = ['loc'=>fix_sing($coo)];
			} else {
				echo "$n -> ..(else)\n";
			}
		}
		ksort($pmx);
		$res->folders[(string)$folder->name] = $pmx;
	}
	ksort($pms['Cities']);

	return $res;
}


$file = empty($_GET['file'])? $def_input: $_GET['file'];
$kml = simplexml_load_file($file);

$data = load_data($kml, isset($ini['special'])? $ini['special']: null);
print_r($data);

#return;
#print_r($pms);

$stylealias = isset($ini['alias'])? $ini['alias']: [];
$styles = isset($ini['styles'])? $ini['styles']: [];

$xml = simplexml_load_string("<kml xmlns='http://www.opengis.net/kml/2.2'><Document><name>$docname</name></Document></kml>");
$doc = $xml->Document;
foreach($pms as $f=>$pmx) {
	if($f=='AD 1100') continue;
	if($f=='Deltas') continue;
	$fold = $doc->addChild('Folder');
	$fold->addChild('name',$f);
	foreach($pmx as $name=>$pmd) {
		$pm = $fold->addChild('Placemark');
		$pm->addChild('name',$name);
		if(isset($pmd['loc'])) {
			$pm->addChild('styleUrl','#city');
			$p = $pm->addChild('Point');
			$p->addChild('coordinates',$pmd['loc']);
		} elseif(isset($pmd['line'])) {
			$pm->addChild('styleUrl','#line');
			$m = $pm->addChild('MultiGeometry');
			foreach($pmd['line'] as $r) {
				$l = $m->addChild('LineString');
				$l->addChild('coordinates',implode(' ',$r));
			}
		} else {
			$l=preg_replace(['{\W+}','{^\W+|\W+$}'],['-',''],strtolower($name));
			if(isset($stylealias[$l])) $l = $stylealias[$l];
			$pm->addChild('styleUrl',"#area-$l");
			if(!isset($styles[$l])) $styles[$l] = [];
			$m = $pm->addChild('MultiGeometry');
			foreach($pmd as $r) {
				$p = $m->addChild('Polygon');
				$ob = array_pop($r);
				$obi = $p->addChild('outerBoundaryIs');
				$obl = $obi->addChild('LinearRing');
				$obl->addChild('coordinates',implode(' ',$ob));
				foreach($r as $ib) {
					$ibi = $p->addChild('innerBoundaryIs');
					$ibl = $ibi->addChild('LinearRing');
					$ibl->addChild('coordinates',implode(' ',$ib));
				}
			}
		}
		#foreach($pm);
		#print_r([$name,$pmd]);
		echo "$name\n";
	}
}

ksort($styles);
$ns = count($styles);
$i = 0;
$pt = $doc->addChild('Style');
$pt['id'] = 'point';
$pt->IconStyle->color = 'ff9966ff';
$pt->IconStyle->Icon->href = 'http://www.gstatic.com/mapspro/images/stock/503-wht-blank_maps.png';
$ln = $doc->addChild('Style');
$ln['id'] = 'line-normal';
$ln->LineStyle->color = 'ff000000';
$ln->LineStyle->width = 1;
$ln = $doc->addChild('Style');
$ln['id'] = 'line-highlight';
$ln->LineStyle->color = 'ff000000';
$ln->LineStyle->width = 1;
$sm = $doc->addChild('StyleMap');
$sm['id'] = "line";
$p = $sm->addChild('Pair');
$p->key = 'normal';
$p->styleUrl = "#line-normal";
$p = $sm->addChild('Pair');
$p->key = 'highlight';
$p->styleUrl = "#line-highlight";
foreach($styles as $l=>$rgb) {
	$style = "area-$l";
	if(empty($rgb)) {
		$d = $i*(6.0/$ns);
		switch(($j=(int)$d)) {
		case 6:
		case 0: $rgb = [1,$d-$j,0]; break;
		case 1: $rgb = [1-$d+$j,1,0]; break;
		case 2: $rgb = [0,1,$d-$j]; break;
		case 3: $rgb = [0,1-$d+$j,1]; break;
		case 4: $rgb = [$d-$j,0,1]; break;
		case 5: $rgb = [1,0,1-$d+$j]; break;
		}
		++$i;
	}
	$nl = $doc->addChild('Style');
	$nl['id'] = "$style-normal";
	$nl->LineStyle->color = sprintf('ff%02x%02x%02x',round(255*$rgb[2]),round(255*$rgb[1]),round(255*$rgb[0]));
	$nl->LineStyle->width = 1;
	$nl->PolyStyle->color = sprintf('55%02x%02x%02x',round(255*$rgb[2]),round(255*$rgb[1]),round(255*$rgb[0]));
	$hl = $doc->addChild('Style');
	$hl['id'] = "$style-highlight";
	$hl->LineStyle->color = sprintf('ff%02x%02x%02x',round(255*$rgb[2]),round(255*$rgb[1]),round(255*$rgb[0]));
	$hl->LineStyle->width = 2;
	$hl->PolyStyle->color = sprintf('aa%02x%02x%02x',round(255*$rgb[2]),round(255*$rgb[1]),round(255*$rgb[0]));
	$sm = $doc->addChild('StyleMap');
	$sm['id'] = "$style";
	$p = $sm->addChild('Pair');
	$p->key = 'normal';
	$p->styleUrl = "#$style-normal";
	$p = $sm->addChild('Pair');
	$p->key = 'highlight';
	$p->styleUrl = "#$style-highlight";
}

$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
$xml_text = $dom->saveXML($dom->documentElement);
$xml_text = preg_replace('/  /',"\t",$xml_text);
file_put_contents($def_output, "<?xml version='1.0' encoding='UTF-8'?>\n".$xml_text);

?>
