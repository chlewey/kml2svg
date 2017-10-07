#!/usr/bin/python3

from math import *
from points import *
from kml import kml
from configparser import ConfigParser
#from svg import svg

def str2key(name):
	pass

class MapItem(object):
	def __init__( self, name ):
		self.__name = name
		self.__key = str2key(name)
	
	def move( self, ini, end ): return False
	
	def reline( self, line ): return False
	
	def xml( node='Placemark' ):
		return simplexml_load_string("<{0}><name>{1}</name></{0}>".format(node, self.__name))

class Folder(MapItem):
	pass

class Folder(MapItem):
	pass

class MapArea(MapItem):
	pass

class MapLine(MapItem):
	pass

class MapPoint(MapItem):
	pass

class MapData(MapItem):
	def __init__(self, obj, params):
		self.__kml = obj
		process = params['process'] or 'process'
		points  = params['points']  or 'points'
		trash   = params['trash']   or 'trash'
		ignore  = params['ignore']  or 'ignore'
		
		MapItem.__init__(self, obj['name'])
		self.__folders = []
		self.__process = process
		self.__points = points
		self.__trash = trash
		self.__ignore = ignore
	
	def transform(self):
		pass
		
	def stats(self, todo):
		pass

	def redux(self, todo):
		pass
		
	def write(self, fn):
		self.__kml.write(fn);

class Params:
	def __init__(self, args):
		self.__dict = dict()
		self.__args = args
		self.__config = ConfigParser(strict=False)
		inifile = args['ini-file']
		if inifile: self.__config.read(inifile)
		path = self.config('file','path','.')
		opath = self.config('file','output_path',path)
		ofile = self.config('file','output_file')
		ipath = self.config('file','input_path',path)
		ifile = self.config('file','input_file')
		self['input'] = ipath.rstrip('/')+'/'+ifile
		if args['output-file']:
			self['output'] = args['output-file']
		else:
			self['output'] = opath.rstrip('/')+'/'+ofile
	
	def config(self, key, sub, default=None):
		try:
			ans = self.__config[key][sub].strip('"')
		except KeyError:
			ans = default
		return ans
	
	def __getitem__(self, key):
		if key in self.__dict.keys():
			return self.__dict[key]
		if key in self.__args.keys():
			return self.__args[key]
		if key in self.__config.sections():
			return dict(self.__config[key])
		if isinstance(key, int) and int<self.__args.count():
			return self.__args[key]
		return None
		
	def __setitem__(self, key, val):
		if key in self.__args.keys():
			raise KeyError
		if key in self.__config.sections():
			raise KeyError
		if isinstance(key, int):
			raise KeyError
		self.__dict[key] = val

	def dump(self):
		keys = self.__dict.keys()
		keys |= self.__args.keys()
		for k in keys:
			print( "{}:\t{}".format(k, self[k]) )
		#for s in self.__config.sections():
		#	print( "{}:\t{}".format(s, self[s]) )
	
def fixmap(params,ifn=None):
	ifn = ifn or params['input']
	ofn = params['overwrite'] and ifn or params['output']
	
	eprint('INPUT FILE:',ifn)
	obj = kml(ifn)
	data = MapData(obj, params)
	data.transform()
	data.stats( params['stats'] )
	data.redux( params['ofn'] )
	data.write( ofn )


"""	
$file = empty($_GET['file'])? $def_input: $_GET['file'];
$kml = simplexml_load_file($file);

$data = new map_data($kml, isset($ini['special'])? $ini['special']: null);
$data->transform();
if(isset($ini['stats'])) $data->stats($ini['stats']);
if(isset($ini[$def_output]['folders']))
	$xml = $data->xml('Document',explode(',', $ini[$def_output]['folders']));
else
	$xml = $data->xml();

$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
$xml_text = $dom->saveXML($dom->documentElement);
$xml_text = preg_replace('/  /',"\t",$xml_text);
file_put_contents($def_output, "<?xml version='1.0' encoding='UTF-8'?>\n".$xml_text);

header('Content-type: application/vnd.google-earth.kml+xml; charset=utf-8');
header('Content-type: text/xml; charset=utf-8');
echo "<?xml version='1.0' encoding='UTF-8'?>\n";
echo $xml_text;
"""

if __name__ == '__main__':

	import sys
	def eprint(*args, **kwargs):
		print(*args, file=sys.stderr, **kwargs)
	
	def __help():
		me = sys.argv[0]
		print("""{0}:
Usage:
	{0} OPTIONS FILE"

Options:
	-c <file>	Create ini file
	-i <file>	Use ini file
	-o <file>	Output filename
	-m		Overwrite input filename
	-h		Print help
""".format(me))
		
	from arghndlr import posixargs
	args = posixargs()
	args.setkeys({'i':'ini-file','o':'output-file'})
	args.setflags({'m':'overwrite','x':'headonly'})
	args.sethelp(__help)
	args.setaction( ('version','v'), "{} Version 0.1".format(sys.argv[0]) )
	args.process()
	#args.dump()
	
	params = Params(args);
	params.dump()
	
	if(args.count() > 1):
		for i in range(1,args.count()):
			fixmap(params, args[i])
	else:
		fixmap(params)
