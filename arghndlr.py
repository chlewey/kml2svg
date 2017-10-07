#!/usr/bin/python3

import sys

class argmeta(type):
	def __len__(self):
		return self.count()


class arghndlr(object, metaclass=argmeta):
	def __init__( self ):
		self.__actions = dict()
		self.__keys = dict()
		self.__flags = set()
		self.__setflags = set()
		self.__alias = dict()
		self.__errors = []
		self.__descs = dict()
		self.__free = []
		self.__ons = dict()
		self.__offs = dict()
		self.__key = False

	def setkeys( self, keys, defaults=None ):
		if isinstance( keys, str ):
			self.__keys[keys] = defaults
		elif isinstance( keys, list ):
			if isinstance( defaults, list ):
				n = min(len(keys),len(defaults))
				for i in range(n):
					self.__keys[keys[i]] = defaults[i]
				for i in range(n,len(keys)):
					self.__keys[keys[i]] = None
			else:
				for key in keys:
					self.__keys[key] = defaults
		elif isinstance( keys, dict ):
			for nick,key in keys.items():
				self.setalias(nick,key)
				try:
					self.__keys[key] = defaults[nick]
				except KeyError:
					self.__keys[key] = None
				except TypeError:
					self.__keys[key] = defaults
		elif isinstance( keys, set ):
			for key in keys:
				self.__keys[key] = defaults

	def setdefault( self, key, default ):
		self.__keys[key] = default

	def setalias( self, alias, key='' ):
		if isinstance( alias, dict ):
			for nick,key in alias.items():
				self.__alias[nick] = key
		else:
			self.__alias[alias] = key

	def setflags( self, flags, on=False ):
		if isinstance( flags, set ):
			self.__flags |= flags
			if on:
				self.__setflags |= flags
		elif isinstance( flags, list ):
			self.__flags |= set(flags)
			if on:
				self.__setflags |= set(flags)
		elif isinstance( flags, dict ):
			self.__flags |= set(flags.values())
			if on:
				self.__setflags |= set(flags.values())
			for nick,flag in flags.items():
				self.setalias(nick,flag)
		else:
			self.__flags += flags;
			if on:
				self.__setflags += flags
	
	def setaction( self, word, function ):
		if isinstance( word, tuple ):
			nick = word[1]
			word = word[0]
			self.setalias( nick, word )
		self.__actions[word] = function
	
	def sethelp( self, function, word='help', alias='h', bang=True ):
		if alias is not None:
			self.setalias( alias, word )
		if bang:
			self.setalias( '?', word )
		self.setaction( word, function )
	
	def setonoff( self, on, off, key=None ):
		if not key:
			key = on
		self.__flags.add(key)
		self.__ons[on] = key
		self.__offs[off] = key

	def process( self, argv=sys.argv ):
		self.resetraise()
		return len(self.__errors)
		
	def flagset( self, flag, value=True ):
		if flag not in self.__flags:
			self.error('Flag %s does not exist.'%flag )
		elif value:
			self.__setflags.add( flag )
		else:
			self.__setflags.discard( flag )

	def flagtoggle( self, flag ):
		if flag not in self.__flags:
			self.error('Flag %s does not exist.'%flag )
		elif flag in self.__setflags:
			self.__setflags.discard( flag )
		else:
			self.__setflags.add( flag )
	
	def handlekey(self, key, strict=False ):
		if key in self.__alias.keys():
			self.handlekey( self.__alias[key], strict )
		elif key in self.__actions.keys():
			action = self.__actions[key]
			if isinstance( action, str ):
				print(action)
				sys.exit(0)
			else:
				sys.exit(action())
		elif key in self.__ons.keys():
			self.__setflags.add(self.__ons[key])
		elif key in self.__offs.keys():
			self.__setflags.discard(self.__offs[key])
		elif key in self.__flags:
			self.flagset( key )
		elif key in self.__keys.keys():
			if(strict):
				self.error( "Key '%s' requires argument."%(key) )
			else:
				self.__key = key
		else:
			self.error( "Undefined key '%s'."%(key) )

	def handlefree(self, arg ):
		if not self.__key:
			self.__free.append(arg)
		else:
			self.__keys[self.__key] = arg
			self.__key = False
		
	def handlekeyval(self, key, val ):
		self.resetraise()
		if key in self.__keys.keys():
			self.__keys[key] = val
		elif key in self.__flags:
			self.flagset( key, val.lower() not in { '','0','off','false','no' } )
		
	def count( self ):
		return len(self.__free)
		
	def __getitem__( self, key ):
		if isinstance( key, str ):
			if key in self.__flags:
				return key in self.__setflags
			if key in self.__keys.keys():
				return self.__keys[key]
			if key in self.__alias.keys():
				return self.__getitem__( self.__alias[key] )
			return None
		
		elif isinstance( key, int ):
			if key >= self.count():
				return None
			return self.__free[key]
	
	def keys( self ):
		ans = self.__flags
		ans |= self.__keys.keys()
		return ans;
			
	def waitkey( self ):
		return self.__key

	def resetwait( self, value=False ):
		if value and self.__key:
			self.handlekeyval( self.__key, value );
		self.__key = False

	def resetraise( self ):
		if self.__key:
			self.error( "Key '%s' requires value"%(self.__key) )
		self.__key = False
		
	def error( self, message ):
		self.__errors.append( message )
			
	def debug( self ):
		if self.__errors:
			print("Errors:")
		for line in self.__errors:
			print(line)
	
	def dump( self ):
		A = vars(self)
		for k,v in A.items():
			print("%-20s: %s"%(k,v))


class posixargs(arghndlr, metaclass=argmeta):
	def process( self, argv=sys.argv ):
		for arg in argv:
			if arg[0:2]=='--':
				self.resetraise()
				parts = arg[2:].split('=')
				if len(parts)==1:
					self.handlekey( parts[0], True )
				else:
					self.handlekeyval( parts[0], '='.join(parts[1:]) )

			elif arg[0]=='-':
				self.resetraise()
				for c in arg[1:]:
					self.handlekey( c )

			else:
				self.handlefree( arg )
		return arghndlr.process(self)

class freeargs(arghndlr, metaclass=argmeta):
	def process( self, argv=sys.argv ):
		pass

DOS_STRICT = 1
DOS_HYPHEN = 2
DOS_AUTO = 3
class dosargs(arghndlr, metaclass=argmeta):
	def __init__( self, method=DOS_AUTO ):
		arghndlr.__init__(self)

	def process( self, argv=sys.argv ):
		pass

if __name__ == '__main__':
	
	args = posixargs()
	args.setflags({'a','beta','c'})
	args.setflags({'c','d'},True)
	args.setkeys(['uno','dos'],['1'])
	args.setonoff( 'd','e' )
	args.setonoff( 'f','g','flag' )
	args.setalias( 'u', 'uno' )
	args.setalias( {'b':'beta'} )
	if args.process():
		args.debug()

	A = vars(args)
	for k,v in A.items():
		print("%-20s: %s"%(k,v))
