<?php
header('Content-type: text/plain; charset=utf-8');

$ini = parse_ini_file("fixmap2.ini", true);
#print_r($ini);

$def_path = isset($ini['file']['path'])? $ini['file']['path']: '.';
$def_ipath = isset($ini['file']['input_path'])? $ini['file']['input_path']: $def_path;
$def_opath = isset($ini['file']['output_path'])? $ini['file']['output_path']: $def_path;

$def_input = isset($ini['file']['input_file'])? rtrim($def_ipath,'/').'/'.$ini['file']['input_file']: rtrim($def_ipath,'/').'/default.kml';
$def_output = isset($ini['file']['output_file'])? rtrim($def_opath,'/').'/'.$ini['file']['output_file']: rtrim($def_opath,'/').'/fixes.kml';

$config = $ini['config'];
$aliases = isset($ini['alias'])? $ini['alias']: [];
$styles = isset($ini['style'])? $ini['style']: [];

function move_coor(string $co, $di, $ac) {
	$t = explode(',',$co);
	$sph = sin(deg2rad($t[1]));
	$cph = cos(deg2rad($t[1]));
	$dx = sin(deg2rad($di))*sin(deg2rad($ac));
	$dy = sin(deg2rad($di))*cos(deg2rad($ac));
	$dz = cos(deg2rad($di));
	$x = $dx;
	$y = $dy*$cph + $dz*$sph;
	$z = $dz*$cph - $dy*$sph;
	$r = sqrt($x*$x+$z*$z);
	$th = atan2($x,$z);
	$ph = atan2($y,$r);
	$t[0] += rad2deg($th);
	$t[1] = rad2deg($ph);
	return fix_sing(implode(',',$t),5);
}

function circle(string $co, $rad, $n=12) {
	$di = $rad*0.000009;
	$ans = [];
	for($i=0;$i<=$n;$i++) {
		$ac = $i*360/$n;
		$ans[$i] = move_coor($co,$di,$ac);
	}
	return $ans;
}

function fix_sing(string $co,$pre=4) {
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;
	$t = explode(',',$co);
	$t[0] = round($t[0],$pre);
	$t[1] = round($t[1],$pre);
	$t[2] = 0;
	return implode(',',$t);
}

function fix_coor(string $coo,$pre=4) {
	$r = explode(' ', $coo);
	for($i=0;$i<count($r);++$i)
		$r[$i] = fix_sing($r[$i],$pre);
	return $r;
}

function vector($coo, $idx=null) {
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;

	$c = explode(',',$coo);
	$th = deg2rad($c[0]-$lon);
	$ph = deg2rad($c[1]);
	return [3600*sin($th)*cos($ph), 3600*sin($ph), 3600*cos($th)*cos($ph)];
}

function vprod($v1, $v2) {
	return [$v1[1]*$v2[2]-$v1[2]*$v2[1],$v1[2]*$v2[0]-$v1[0]*$v2[2],$v1[0]*$v2[1]-$v1[1]*$v2[0]];
}

function eprod($v1, $v2) {
	return $v1[0]*$v2[0]+$v1[1]*$v2[1]+$v1[2]*$v2[2];
}

function vadd($v1, $v2) {
	return [$v1[0]+$v2[0],$v1[1]+$v2[1],$v1[2]+$v2[2]];
}

function vdif($v1, $v2) {
	return [$v1[0]-$v2[0],$v1[1]-$v2[1],$v1[2]-$v2[2]];
}

function varea($varray) {
	$c = [0.0, 0.0, 0.0];
	for($i=1;$i<count($varray);$i++) {
		$c = vadd($c, vprod($varray[$i-1],$varray[$i]));
	}
	return [$c[0]/2,$c[1]/2,$c[2]/2];
}

function latlong($v) {
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;
	$ln = atan2($v[0],$v[2]);
	$rn = sqrt($v[0]*$v[0]+$v[2]*$v[2]);
	$lt = atan2($rn,$v[1]);
	return fix_sing(sprintf('%f,%f', rad2deg($ln)+$lon, rad2deg($lt) ));
}

function norm($coo, $idx=null) {
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;

	$c = explode(',',$coo);
	$th = deg2rad($c[0]-$lon);
	$ph = deg2rad($c[1]);
	return [$th*6378, sin($ph)*6357, 0.0];
}

function narea($narray) {
	$v = varea($narray);
	return $v[2];
}

function line_insert($co1, $co2, $cyclic=false) {
	$m = count($co1);
	$n = count($co2);
	$ini = $co2[0];
	$end = $co2[$n-1];
	$ii = array_search($ini, $co1);
	$ie = array_search($end, $co1);
	if($cyclic) {
		#echo "cycle: $m, insert: $n; $ini at $ii; $end at $ie";
		#print_r($co1);
		if($ii===false || $ie===false)
			return false;
		if($ie==0) {
			if($co1[0] != $co1[$m-1])
				return false;
			$ie = $m-1;
		}
		if($ii<$ie) {
			$head = array_slice($co1, 0, $ii);
			$tail = array_slice($co1, $ie+1);
			#echo " [SIZES: ".implode(' ',[count($head),count($co2),count($tail)])."]";
			return array_merge($head, $co2, $tail);
		}
		$mid = array_slice($co1,$ie+1,$ii-$ie);
		#echo " [SIZES (mid): ".implode(' ',[count($mid),count($co2)])."]";
		return array_merge($co2,$mid);
	} else {
		#echo "trying to insert $n points in $m line ($ii,$ie)";
		if($ii===false) {
			if($ie===0) {
				$tail = array_slice($co1,1);
				return array_merge($co2,$tail);
			} else
				return false;
		}
		if($ie===false) {
			if($ii==$m-1) {
				$tail = array_slice($co2,1);
				return array_merge($co1,$tail);
			} else
				return false;
		}
		if($ie<$ii)
			return false;
		$head = array_slice($co1, 0, $ii);
		$tail = array_slice($co1, $ie+1);
		return arra_merge($head, $co2, $tail);
	}
}

function line_append($co1, $co2) {
	$m = count($co1);
	$n = count($co2);
	$ini = $co2[0];
	$ii = array_search($ini, $co1);

	if($ii===false)
		return false;

	$head = array_slice($co1, 0, $ii);
	$tail = array_slice($co1, $ii+1);
	return array_merge($head, $co2, $tail);
}

function line_prepend($co1, $co2) {
	$m = count($co1);
	$n = count($co2);
	$end = $co2[$n-1];
	$ie = array_search($end, $co1);
	if($ie===0 && $co1[$m-1]==$end)
		$ie = $m-1;

	if($ie===false)
		return false;

	$head = array_slice($co1, 0, $ie);
	$tail = array_slice($co1, $ie+1);
	### echo "Inserting a possition $ie (".count($head).",".count($co2).",".count($tail).") \n";
	### if(count($co1)<20)
		### echo implode(" ",$co1)."\n";
	return array_merge($head, $co2, $tail);
}

function str2key($str) {
	$n = preg_replace([
		'/à|á|â|ä/','/æ/',
		'/è|é|ê|ë/',
		'/ì|í|î|ï/',
		'/ò|ó|ô|ö/',
		'/ù|ú|û|ü/',
		'/\W+/',
		'/^\W+|\W+$/'
		],[
		'a','ae',
		'e','i','o','u',
		'-',''
		],mb_strtolower($str));
	return $n;
}

class map_item {
	function __construct(string $name) {
		$this->name = $name;
		$this->key = str2key($name);
	}
	
	function move($from,$to) { return false; }
	function reline($line) { return false; }
	function xml($node='Placemark') { return simplexml_load_string("<$node><name>{$this->name}</name></$node>"); }
}

class folder extends map_item {
	function __construct($name) {
		map_item::__construct($name);
		$this->areas=[];
		$this->lines=[];
		$this->points=[];
		$this->params = [];
		
		global $ini;
		if(isset($ini['folder'])) {
			foreach($ini['folder'] as $key=>$val) {
				$this->params[$key] = $val;
			}
		}
		if(isset($ini[$name])) {
			foreach($ini[$name] as $key=>$val) {
				$this->params[$key] = $val;
			}
		}
	}
	
	function add($obj) {
		$n = $obj->name;
		if(is_a($obj,'map_area')) {
			if(isset($this->areas[$n]))
				$this->areas[$n]->add_area($obj);
			else
				$this->areas[$n] = $obj;
		} elseif(is_a($obj,'map_line')) {
			$this->lines[] = $obj;
		} elseif(is_a($obj,'map_point')) {
			$this->points[$n] = $obj;
		} elseif(is_a($obj,'map_poly')) {
			if(isset($this->areas[$n]))
				$this->areas[$n]->add_area($obj);
			else
				$this->areas[$n] = new map_area($obj);
		} else return false;
		return true;
	}

	function move($from,$to) {
		foreach($this->areas as $i=>$area)
			$this->areas[$i]->move($from,$to);

		foreach($this->lines as $i=>$line)
			$this->lines[$i]->move($from,$to);

		foreach($this->points as $i=>$point)
			$this->points[$i]->move($from,$to);
	}

	function reline($line) {
		foreach($this->areas as $i=>$area)
			$this->areas[$i]->reline($line);
	}
	
	function empty() {
		return empty($this->areas) && empty($this->lines) && empty($this->points);
	}
	
	function xml($node='Folder') {
		$xml = map_item::xml($node);
		$dom = dom_import_simplexml($xml);
		foreach($this->areas as $area) {
			$dom_a = dom_import_simplexml($area->xml());
			$dom_a = $dom->ownerDocument->importNode($dom_a, true);
			$dom->appendChild($dom_a);
		}
		foreach($this->lines as $line) {
			if(is_null($line)) continue;
			$dom_l = dom_import_simplexml($line->xml());
			$dom_l = $dom->ownerDocument->importNode($dom_l, true);
			$dom->appendChild($dom_l);
		}
		foreach($this->points as $point) {
			$dom_p = dom_import_simplexml($point->xml());
			$dom_p = $dom->ownerDocument->importNode($dom_p, true);
			$dom->appendChild($dom_p);
		}

		return $xml;
	}
}

class map_poly extends map_item {
	function __construct($pm_poly,$name) {
		map_item::__construct($name);
		if(is_array($pm_poly)) {
			$this->outer = $pm_poly;
			$this->inner = [];
		} else {
			$coo = (string) $pm_poly->outerBoundaryIs->LinearRing->coordinates;
			$this->outer = fix_coor($coo);
			$this->inner = [];
			foreach($pm_poly->innerBoundaryIs as $ib) {
				$coo = (string) $ib->LinearRing->coordinates;
				$this->inner[] = fix_coor($coo);
			}
		}
	}
	
	function area_vector() {
		$vects = array_walk($this->outer, 'vector');
		$area = $x = varea($vects);
		foreach($this->inner as $c) {
			$v = array_walk($c, 'vector');
			$y = varea($vects);
			$f = eprod($x,$y) > 0.0;
			$area = $f? vdif($area, $y): vadd($area, $y);
		}
	}

	function area_norm() {
		$nn = array_walk($this->outer, 'norm');
		$area = $x = narea($nn);
		foreach($this->inner as $c) {
			$nn = array_walk($c, 'norm');
			$y = narea($nn);
			$area+= $y>0.0? -$y: $y;
		}
	}
	
	function mid_point() {
		return latlong($this->area_vector());
	}
	
	function move($from,$to) {
		if(($i = array_search($from,$this->outer))!==false)
			$this->outer[$i] = $to;
		foreach($this->inner as $ib) {
			if(($i = array_search($from,$ib))!==false)
				$ib[$i] = $to;
		}
	}
	
	static function __reline($ring, $line, $append=false) {
		return $append?
			line_append($ring, $line):
			line_insert($ring, $line, true);
	}

	function reline($line) {
		if(is_a($line,'map_line')) {
			$coo = $line->coord;
		} elseif(is_array($line)) {
			$coo = $line;
		} elseif(is_string($line)) {
			$coo = fix_coor($line);
		} else {
			### echo "Undefined line type: ";
			### print_r($line);
			return false;
		}
		if(isset($line->append))
			$f = line_append($this->outer, $coo);
		elseif(isset($line->prepend)) {
			### echo "Prepending segment of ".count($coo)." from {$line->prepend} into {$this->name} at ".count($this->outer)." point outer ring.\n";
			$f = line_prepend($this->outer, $coo);
		}
		else
			$f = line_insert($this->outer, $coo, true);
		if(!empty($f))
			return $this->outer = $f;

		foreach($this->inner as $i=>$ib) {
			if(isset($line->append))
				$f = line_append($ib, $coo);
			elseif(isset($line->prepend)) {
				### echo "Prepending segment of ".count($coo)." from {$line->prepend} into {$this->name} at ".count($ib)." point inner ring.\n";
				$f = line_prepend($ib, $coo);
			}
			else
				$f = line_insert($ib, $coo, true);
			if(empty($f)) continue;
			return $this->inner[$i] = $f;
		}
		return false;
	}
	
	static function __subline($ring, $ini, $end) {
		$ii = array_search($ini, $ring);
		$ie = array_search($end, $ring);
		if($ii===false || $ie===false) return false;
		if($ii<$ie)
			return array_slice($ring, $ii, $ie-$ii+1);
		else {
			$tail = array_slice($ring, 1, $ie);
			$head = array_slice($ring, $ii);
			return array_merge($head, $tail);
		}
	}
	
	function subline($ini, $end) {
		$r = map_poly::__subline($this->outer, $ini, $end);
		if(!empty($r)) return $r;
		foreach($this->inner as $ib) {
			$r = map_poly::__subline($ob, $ini, $end);
			if(!empty($r)) return $r;
		}
		return false;
	}

	function has_point($co) {
		return array_search($co,$this->outer)!==false;
	}

	function has_inner($co) {
		foreach($this->inner as $i=>$ib) {
			if(array_search($co,$ib)!==false)
				return $i;
		}
		return false;
	}

	function delete_inner($idx) {
		### echo "<!-- [[[ deleting innerbound #$idx ]]] -->";
		unset($this->inner[$idx]);
	}

	function xml($node='Polygon') {
		if(empty($this->outer))
			return null;
		$xml = simplexml_load_string("<$node/>");
		$dom = dom_import_simplexml($xml);
		$ob = simplexml_load_string("<outerBoundaryIs><LinearRing></LinearRing></outerBoundaryIs>");
		$ob->LinearRing->coordinates = implode(' ',$this->outer);
		$obdom = dom_import_simplexml($ob);
		$obdom  = $dom->ownerDocument->importNode($obdom, true);
		$dom->appendChild($obdom);

		foreach($this->inner as $ib) {
			$ibxml = simplexml_load_string("<innerBoundaryIs><LinearRing></LinearRing></innerBoundaryIs>");
			$ibxml->LinearRing->coordinates = implode(' ',$ib);
			$ibdom = dom_import_simplexml($ibxml);
			$ibdom  = $dom->ownerDocument->importNode($ibdom, true);
			$dom->appendChild($ibdom);
		}
		return $xml;
	}
}

class map_area extends map_item {
	function __construct($placemark) {
		map_item::__construct($name = $placemark->name);

		$this->polys = [];
		if(is_a($placemark, 'map_poly')) {
			$this->polys[] = $placemark;
		} elseif(isset($placemark->MultiGeometry)) {
			foreach($placemark->MultiGeometry->Polygon as $poly)
				$this->polys[] = new map_poly($poly,$name);
		} elseif (isset($placemark->Polygon)) {
			$this->polys[] = new map_poly($placemark->Polygon,$name);
		} else {
			### echo "Unknown type of placemark";
			### print_r($placemark);
		}
	}
	
	function add_area($placemark) {
		$name = $this->name;
		if(is_a($placemark, 'map_poly')) {
			$this->polys[] = $placemark;
		} elseif(isset($placemark->MultiGeometry)) {
			foreach($placemark->MultiGeometry->Polygon as $poly)
				$this->polys[] = new map_poly($poly,$name);
		} elseif (isset($placemark->Polygon)) {
			$this->polys[] = new map_poly($placemark->Polygon,$name);
		} else {
			### echo "Unknown type of placemark";
			### print_r($placemark);
		}
	}
	
	function area_vector() {
		$c = [0.0, 0.0, 0.0];
		foreach($this->polys as $p) {
			$c = vadd($c, $p->area_vector());
		}
		return $c;
	}

	function area_norm() {
		$a = 0.0;
		foreach($this->polys as $p) {
			$a+= $p->area_norm();
		}
		return $a;
	}

	function mid_point() {
		return latlong($this->area_vector());
	}
	
	function move($from,$to) {
		foreach($this->polys as $i=>$poly) {
			$this->polys[$i]->move($from,$to);
		}
	}

	function reline($line) {
		if(isset($line->delete))
			return $this->delete($line);
		$I = count($this->polys);
		### print_r($line);
		foreach($this->polys as $i=>$poly) {
			$n = count($line->coord);
			$a = $line->coord[0];
			$b = $line->coord[$n-1];
			### echo "Inserting $n line (from $a to $b) into {$this->name} polygon #$i (out of $I).\n";
			$f = $this->polys[$i]->reline($line);
			### echo $f? "(done)\n": "(nop)\n";
			if($f!==false) return $f;
		}
		return false;
	}
	
	function delete($line) {
		if(is_array($line)) $co = $line[0];
		elseif(is_string($line)) $co = $line;
		elseif(is_a($line,'map_line')) $co = $line->coord[0];
		### echo "<!-- [[ trying to delete polygon with coordinates '$co' in {$this->name} ]] -->\n";
		foreach($this->polys as $i=>$poly) {
			if($poly->has_point($co)) {
				### echo "<!-- [[[ Deleting outer boundary #$i ]]] -->\n";
				unset($this->polys[$i]);
				### echo "<-- ".implode(', ',array_keys($this->polys))." -->\n";
				return true;
			}
			if(($j=$poly->has_inner($co))!==false) {
				### echo "<!-- [[[ Deleting inner boundary #$i.$j ]]] -->\n";
				$poly->delete_inner($j);
				return true;
			}
		}
		### echo "<!-- [[ could not delete polygon with coordinates '$co' in {$this->name} ]] -->\n";
		return false;
	}
	
	function trans($data) {
	}
	
	function subline($ini, $end) {
		foreach($this->polys as $i=>$poly) {
			$r = $poly->subline($ini, $end);
			if(!empty($r)) {
				$this->idx = $i;
				return $r;
			}
		}
		return false;
	}
	
	function find_poly($ini, $end) {
		foreach($this->polys as $poly) {
			if($poly->has_point($ini) and $poly->has_point($end))
				return $poly;
		}
		return false;
	}

	function xml($node='Placemark') {
		global $aliases, $styles;
		$xml = map_item::xml();
		$l = $this->key;
		if(isset($aliases[$l])) $l=$aliases[$l];
		$style = "area-$l";
		if(!isset($styles[$style])) $styles[$style] = '';
		$xml->styleUrl = "#$style";
		if(count($this->polys)>1) {
			$xml->addChild('MultiGeometry');
			$dom = dom_import_simplexml($xml->MultiGeometry);
		} else {
			$dom = dom_import_simplexml($xml);
		}
		foreach($this->polys as $poly) {
			$polydom = dom_import_simplexml($poly->xml());
			$polydom  = $dom->ownerDocument->importNode($polydom, true);
			$dom->appendChild($polydom);
		}
		return $xml;
	}
}

class map_line extends map_item {
	function __construct($mixed, string $name, $reversed = false) {
		map_item::__construct($name);
		if(is_array($mixed)) {
			$coord = $mixed;
		} elseif(is_string($mixed)) {
			$coord = fix_coor($mixed);
		} elseif(is_a($mixed,'map_line')) {
			$coord = $mixed->coord;
		} elseif(isset($mixed->coordinates)) {
			$coord = fix_coor($mixed->coordinates);
		} elseif(isset($mixed->LineString)) {
			$coord = fix_coor($mixed->LineString->coordinates);
		} else {
			$coord = [];
		}
		$this->coord = $reversed? array_reverse($coord): $coord;
	}
	
	function reverse($name=null) {
		if(is_null($name)) {
			$this->coord = array_reverse($this->coord);
			return $this;
		}
		return new map_line($this->coord, $name, true);
	}

	function move($from,$to) {
		if(($i = array_search($from,$this->coord))!==false)
			$this->coord[$i] = $to;
	}

	function reline($line) {
		if(is_a($line,'map_line')) {
			$coo = $line->coord;
		} elseif(is_array($line)) {
			$coo = $line;
		} elseif(is_string($line)) {
			$coo = fix_coor($line);
		} else {
			### echo "Undefined line type: ";
			### print_r($line);
			return false;
		}
		
		$f = line_insert($this->coord, $coo, true);
		if(!empty($f))
			$this->coord = $f;
		return $f;
	}

	function xml($node='Placemark') {
		$xml = map_item::xml($node);
		$xml->styleUrl = "#line-default";
		$xml->addChild('LineString');
		$xml->LineString->coordinates = implode(' ',$this->coord);
		return $xml;
	}
}

class map_point extends map_item {
	function __construct($pm_point, string $name) {
		map_item::__construct($name);
		$coo = is_string($pm_point)? $pm_point: (string) $pm_point->coordinates;
		$this->coord = fix_sing($coo);
	}

	function move($from,$to) {
		if($this->coord == $from)
			return $this->coord = $to;
		return false;
	}

	function xml($node='Placemark') {
		$xml = map_item::xml($node);
		$xml->styleUrl = "#point-default";
		$xml->addChild('Point');
		$xml->Point->coordinates = $this->coord;
		return $xml;
	}
}

class map_data extends map_item {

	function __construct($kml, $specials=null) {
		$process = isset($specials['process'])? $specials['process']: 'process';
		$points = isset($specials['points'])? $specials['points']: 'points';
		$trash = isset($specials['trash'])? $specials['trash']: 'trash';
		$ignore = isset($specials['ignore'])? $specials['ignore']: [];
		$nomerge = isset($specials['nomerge'])? $specials['nomerge']: [];

		map_item::__construct($kml->Document->name);
		$this->folders = [];
		$this->process = $process;
		$this->points = $points;
		$this->trash = $trash;
		$this->ignore = $ignore;
		$this->nomerge = $nomerge;
		
		$i=0;
		foreach ( $kml->Document->Folder as $folder ) {
			$f = new folder($m=(string)$folder->name);
			$areas = [];
			$lines = [];
			$points = [];
			foreach($folder->Placemark as $pm) {
				$n = (string) $pm->name;

				if(isset($pm->Point)) {
					$points[$n] = new map_point($pm->Point, $n);
				} elseif(isset($pm->LineString)) {
					$lines[] = new map_line($pm->LineString, $n);
				} elseif(isset($areas[$n])) {
					$areas[$n]->add_area($pm);
				} else {
					$areas[$n] = new map_area($pm);
				}
			}

			if(!empty($areas)) {
				ksort($areas);
				$f->areas = $areas;
			}
			if(!empty($lines)) {
				#ksort($lines);
				$f->lines = $lines;
			}
			if(!empty($points)) {
				ksort($points);
				$f->points = $points;
			}
			$this->folders[$m] = $f;
		}
			
		#ksort($pms['Cities']);
	}
	
	function transform() {
		$p = $this->process;
		$moves = [];
		$lines = [];
		if(isset($this->folders[$p])) {
			$data = $this->folders[$p]->lines;
			foreach($data as $action) {
				$n = $action->name;
				if(substr($n,0,4)=='MOVE') {
					$moves[] = $action->coord;
				}
				elseif(preg_match('/^X([S])\s*[:-]\s+([^-]+?)\s*$/', $n, $m)) {
					$n = count($rc = $action->coord);
					$ini = $rc[0];
					$end = $rc[$n-1];
					$cr = array_reverse($rc);
					$nm = $m[2];
					echo "<!-- XS ($nm, from $ini to $end) -->\n";
					echo "<!-- Folders: '".implode("', '", array_keys($this->folders))."' -->\n";
					foreach($this->folders as $fn=>$f) {
						echo "<!-- Folders $fn: '".implode("', '", array_keys($f->areas))."' -->\n";
						if(isset($f->areas[$nm])) {
							echo "<!-- found area $nm in $fn -->\n";
							if($line = $f->areas[$m[2]]->subline($end, $ini)) {
								$i = $f->areas[$nm]->idx;
								echo "<!-- found in $fn (#$i) -->\n";
								$p1 = new map_poly(array_merge($line, array_slice($rc,1)), $nm);
								$l2 = $f->areas[$nm]->subline($ini, $end);
								$ob = array_merge($l2, array_slice($cr,1));
								$f->areas[$nm]->polys[$i]->outer = $ob;
								$f->add($p1);
								break;
							}
						}
						echo "<!-- not found in $fn -->\n";
					}
				}
				elseif(preg_match('/^X([DR])\s*[:-]\s+([^-]+?)\s*$/', $n, $m)) {
					$x = new map_line($action->coord, $m[2], $m[1]=='R');
					if($m[1]=='D') $x->delete = true;
					$lines[] = $x;
				}
				elseif(preg_match('/^X([TD])\s*[:-]\s+([^-]+?)\s+-\s+([^:]*\:)?([^-]+?)\s*$/', $n, $m)) {
					$from = $m[2];
					$to = $m[4];
					$ini = $action->coord[0];
					$end = $action->coord[1];
					$fold = trim($m[3],':');
					if($m[1]=='T') {
						### echo "\n<!-- preparing transfer from $from to $to ($fold) -->\n";
						foreach($this->folders as $f) {
							if(isset($f->areas[$from])) {
								### echo "<!-- area $from found at folder {$f->name} -->\n";
								if($poly = $f->areas[$from]->find_poly($ini, $end)) {
									$poly->name = $to;
									### print_r($poly);
									if($fold)
										$this->folders[$fold]->add($poly);
									else
										$f->add($poly);
									break;
								}
								### echo "<!-- (no poly found with points $ini and $end) -->\n";
							}
						}
					}
					$x = new map_line($action->coord, $m[2], true);
					$x->delete = true;
					$x->bounce = false;
					$lines[] = $x;
					if ($m[1]=='D') {
						$y = new map_line($action->coord, $m[4], true);
						$y->delete = true;
						$y->bounce = true;
						$lines[] = $y;
					}
				}
				elseif(preg_match('/^X([AXYZ])\s*[:-]\s+([^-]+?)\s+-\s+([^-]+?)\s*$/', $n, $m)) {
					### echo "<!-- $n : COPy{$m[1]} from ({$m[2]}) to ({$m[3]}) -->\n";
					$from = $m[2];
					$to = $m[3];
					$ini = $action->coord[0];
					$end = $action->coord[1];
					foreach($this->folders as $f) {
						if(isset($f->areas[$from]))
							if($line = $f->areas[$from]->subline($ini, $end)) break;
					}
					if($line) {
						$x = new map_line($line, $to);
						if($m[1]=='X')
							$x->append = $from;
						elseif($m[1]=='Z')
							$x->prepend = $from;
						else
							$x->insert = $from;
						$lines[] = $x;
						if($m[1]=='A') {
							$x = new map_line($action->coord, $m[2]);
							$x->delete = true;
							$x->XA = true;
							$lines[] = $x;
						}
					}
				}
				elseif(preg_match('/^X([Y])\s*[:-]\s+([^-]+?)\s+-\s+([^-]+)\s+-\s*([^-]+?)\s*$/', $n, $m)) {
					$from = $m[2];
					$to1 = $m[3];
					$to2 = $m[4];
					$ini = $action->coord[0];
					$end = $action->coord[1];
					foreach($this->folders as $f)
						if(isset($f->areas[$from]))
							if($line = $f->areas[$from]->subline($ini, $end)) {
								$x = new map_line($line, $to1);
								$x->insert = $from;
								$lines[] = $x;
								$y = new map_line($line, $to2, true);
								$y->insert = $from;
								$lines[] = $y;
								break;
							}
				}
				elseif(preg_match('/^\s*([^-]+?)\s+-\s+([^-]+?)\s*$/', $n, $m)) {
					### echo "<!-- $n : ({$m[1]}) ({$m[2]}) -->\n";
					$lines[] = new map_line($action->coord, $m[1]);
					$lines[] = new map_line($action->coord, $m[2], true);
				}
				else {
					$lines[] = $action;
				}
			}
		}
		### echo "<!--\n";
		### print_r(['lines'=>$lines, 'moves'=>$moves]);
		### echo "-->\n";
		
		#$lines = array_slice($lines,0,150);
		
		#$MP = $this->folders[$this->points] = new folder($this->points);
		
		foreach($this->folders as $fn=>$folder) {
			if($fn == $p) continue;
			if($fn == $this->points) continue;
			if($fn == $this->trash) continue;
			#echo "FOLDER: $fn -----\n";
			foreach($moves as $move) {
				$from = $move[0];
				$to = $move[1];
				$this->folders[$fn]->move($from,$to);
			}
			foreach($lines as $line) {
				$an = $line->name;
				if(isset($folder->areas[$an]))
					$folder->areas[$an]->reline($line);
			}
			/*
			foreach($folder->lines as $i=>$line) {
				$an = $line->name;
				if(isset($folder->areas[$an])) {
					if(empty($folder->areas[$an]->reline($line)))
						$this->send_trash($line,$fn);
					else
						$folder->lines[$i] = null;
				} else {
					$this->send_trash($line,$fn);
				}
			}
			*/
			/*
			foreach($folder->areas as $i=>$area) {
				$c = $area->mid_point();
				$this->folders[$this->points]->points[$i] = new map_point($c, $i);
			}
			*/
			if(empty($folder->params['target']))
				continue;
			$target = $folder->params['target'];
			if(!isset($this->folders[$target]))
				$this->folders[$target] = new folder($target);
			$rad = isset($folder->params['point'])?
					$folder->params['point'] / 2: 500;

			foreach($folder->points as $i=>$point) {
				$this->folders[$target]->add(new map_poly(circle($point->coord, $rad, 24),$i));
			}
		}
	}
	
	function send_trash($obj,$folder=null) {
		$trash = $this->trash;
		if($folder) {
			$name = $obj->name;
			### echo "<!-- TRASHING $name in folder $folder -->\n";
		}
		if(!isset($this->folders[$trash]))
			$this->folders[$trash] = new folder($trash);
		$this->folders[$trash]->add($obj);
	}
	
	function xml($node='Document') {
		$xml = simplexml_load_string("<kml xmlns='http://www.opengis.net/kml/2.2'><Document><name>{$this->name}</name></Document></kml>");

		$doc = dom_import_simplexml($xml->Document);
		foreach($this->folders as $fn=>$folder) {
			if(in_array($fn,$this->ignore)) continue;
			if($fn==$this->process) continue;
			if($folder->empty()) continue;
			
			$fold = dom_import_simplexml($folder->xml());
			$fold = $doc->ownerDocument->importNode($fold, true);
			$doc->appendChild($fold);
		}

		
		global $styles;
		ksort($styles);
		$c = 0;
		foreach($styles as $l=>$rgb)
			if(empty($rgb)) ++$c;
			
		$i = 0;
		foreach($styles as $l=>$rgb) {
			if(empty($rgb)) {
				$x = 6.0*$i/$c;
				switch($d = (int)$x) {
				case 0: $rgb = sprintf('%02x%02x%02x', 255, round(255*($x-$d)), 0); break;
				case 1: $rgb = sprintf('%02x%02x%02x', round(255*(1+$d-$x)), 255, 0); break;
				case 2: $rgb = sprintf('%02x%02x%02x', 0, 255, round(255*($x-$d))); break;
				case 3: $rgb = sprintf('%02x%02x%02x', 0, round(255*(1+$d-$x)), 255); break;
				case 4: $rgb = sprintf('%02x%02x%02x', round(255*($x-$d)), 0, 255); break;
				case 5: $rgb = sprintf('%02x%02x%02x', 255, 0, round(255*(1+$d-$x))); break;
				}
				++$i;
			}
			add_style($xml->Document, $l, $rgb);
		}

		return $xml;
	}
}

function add_style($doc, $label, $rgb) {
	$bgr = substr($rgb,4,2).substr($rgb,2,2).substr($rgb,0,2);
	$nl = $doc->addChild('Style');
	$nl['id'] = "$label-normal";
	$nl->LineStyle->color = "99$bgr";
	$nl->PolyStyle->color = "55$bgr";
	
	$hl = $doc->addChild('Style');
	$hl['id'] = "$label-highlight";
	$hl->LineStyle->color = "cc$bgr";
	$hl->PolyStyle->color = "aa$bgr";
	
	$sm = $doc->addChild('StyleMap');
	$sm['id'] = "$label";
	
	$p = $sm->addChild('Pair');
	$p->key = 'normal';
	$p->styleUrl = "#$label-normal";

	$p = $sm->addChild('Pair');
	$p->key = 'highlight';
	$p->styleUrl = "#$label-highlight";

	return $sm;
}


$file = empty($_GET['file'])? $def_input: $_GET['file'];
$kml = simplexml_load_file($file);

$data = new map_data($kml, isset($ini['special'])? $ini['special']: null);
$data->transform();

#echo "Hola\n";
$xml = $data->xml();

$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
$xml_text = $dom->saveXML($dom->documentElement);
$xml_text = preg_replace('/  /',"\t",$xml_text);
file_put_contents($def_output, "<?xml version='1.0' encoding='UTF-8'?>\n".$xml_text);

header('Content-type: application/vnd.google-earth.kml+xml; charset=utf-8');
header('Content-type: text/xml; charset=utf-8');
echo $xml_text;

?>
