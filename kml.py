#!/usr/bin/python3

import xml.etree.ElementTree as ET
import re

empty_kml="""<kml xmlns="http://www.opengis.net/kml/2.2"><Document/></kml>"""

ns={'kml': "http://www.opengis.net/kml/2.2",
	'gx': "http://www.google.com/kml/ext/2.2"
}
ns_rev = dict([(y,x) for (x,y) in ns.items()])
ET.register_namespace('', ns['kml']);

def unbrace_ns(name):
	M = re.match('^\{([^{}]*)\}(.*)$',name)
	if M is None: return name
	url = M.group(1)
	#print(url)
	if url not in ns_rev.keys(): return name
	return '{}:{}'.format( ns_rev[url], M.group(2) )

class kml_styles():
	def __init__( self, doc ):
		self.styles = doc.findall('kml:Style')
		self.stylemaps = doc.findall('kml:StyleMap')

class kml_container():
	def __init__( self, element ):
		self.__node = element
		name = element.find('kml:name',ns)
		if name is None: name = element.find('name')
		try:
			self.name = name.text
		except AttributeError:
			self.name = None
		self.folders=[]
		self.marks=[]
		for F in element.findall('kml:Folder', ns ):
			self.folders.append( kml_container(F) );
		for P in element.findall('kml:Placemark', ns ):
			self.marks.append( klm_place(P) );
	
	def __getitem__( self, key, sub=0 ):
		nskey = 'kml:'+key
		ans = self.__node.find( nskey, ns )
		print(nskey, ans)
		return ans.text

	def findall( self, key ):
		return self.__node.findall( key, ns )
		
	def debug( self, tab='' ):
		print( tab+unbrace_ns(self.__node.tag), self.name, "with {} places".format(len(self.marks)) )
		#print( tab+self.__node.tag, self.name, "with {} places".format(len(self.marks)) )
		NS = '{'+ns['kml']+'}'
		for child in self.__node:
			if child.tag in [NS+'Folder', NS+'Style', NS+'Placemark', NS+'StyleMap', NS+'name']: continue
			if child.tag in ['Folder', 'Style', 'Placemark', 'StyleMap', 'name']: continue
			print(tab+'-->', unbrace_ns(child.tag), child.attrib)
		for folder in self.folders:
			folder.debug(tab+'\t')
		for place in self.marks:
			place.debug(tab+'\t')

class klm_place():
	def __init__( self, element ):
		self.__node = element
		name = element.find('kml:name',ns)
		try:
			self.name = name.text
		except AttributeError:
			self.name = None
			ED = element.find('kml:ExtendedData',ns)
			for D in ED:
				if D.get('name')=='Name':
					self.name = D.find('kml:value',ns).text
	
	def type( self ):
		types = ['MultiGeometry','Polygon','LineString','Point']
		for t in types:
			if self.__node.find('kml:'+t,ns):
				return t
	
	def debug( self, tab='' ):
		print( tab+unbrace_ns(self.__node.tag), self.type(), self.name )
		NS = '{'+ns['kml']+'}'
		for child in self.__node:
			print(tab+'-->', unbrace_ns(child.tag), child.attrib)
			if child.tag in [NS+'snippet']:
				for grand in child:
					print(tab+'\t', unbrace_ns(grand.tag), grand.attrib)

class klm_point():
	pass

class klm_line():
	pass

class klm_poly():
	pass

class klm_area():
	pass

class kml:
	def __init__(self, filename=None, string=None):
		if filename:
			self.__tree = ET.parse(filename)
		elif string:
			self.__tree = ET.fromstring(string)
		else:
			self.__tree = ET.fromstring(empty_kml)
		self.__root = self.__tree.getroot()
		self.doc = kml_container(self.__root[0])
		self.styles = kml_styles( self.doc )
	
	def __getitem__(self, key):
		return self.doc[key]
	
	def write( self, filename=None ):
		if filename:
			self.__tree.write( filename )
			
	def debug( self ):
		print( self.__root.tag );
		for k,v in self.__root.attrib.items():
			print( " --> {}: {}".format(k,v) )
		for child in self.__root:
			print(child.tag, child.attrib )
		self.doc.debug()

if __name__ == '__main__':
	
	from arghndlr import posixargs
	args = posixargs()
	args.process()
	
	for i in range(1,args.count()):
		print('\n'+args[i])
		K = kml(args[i])
		K.debug()
