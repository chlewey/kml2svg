#!/usr/bin/python3

import xml.etree.ElementTree as ET

empty_svg="""<svg xmlns="http://www.w3.org/2000/svg"></svg>"""

ns={'svg': "http://www.w3.org/2000/svg",
	'sodipodi': 'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd',
	'inkscape': 'http://www.inkscape.org/namespaces/inkscape',
	'xml': "http://www.w3.org/XML/1998/namespace",
	'dc': "http://purl.org/dc/elements/1.1/",
	'cc': "http://creativecommons.org/ns#",
	'rdf': "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
}

class svg:
	def __init__(self, filename=None, string=None):
		if filename:
			self.__tree = ET.parse(filename)
		elif string:
			self.__tree = ET.fromstring(string)
		else:
			self.__tree = ET.fromstring(empty_svg)
		self.__root = self.__tree.getroot()
		self.tag = self.__root.tag
		self.attrib = self.__root.attrib
	
	def write( self, filename=None ):
		if filename:
			self.__tree.write( filename )

if __name__ == '__main__':
	
	from arghndlr import posixargs
	args = posixargs()
	args.process()
	
	for i in range(1,args.count()):
		print('\n'+args[i])
		S = svg(args[i])
		print(S.tag)
		print(S.attrib)
