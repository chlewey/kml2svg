#!/usr/bin/python3

from math import *

def vecadd(V1,V2):
	if isinstance(V1, tuple) and isinstance(V2, tuple):
		return (V1[0]+V2[0],V1[1]+V2[1],V1[2]+V2[2])
	elif isinstance(V1, list) and isinstance(V2, tuple):
		return [vecadd(T,V2) for T in V1]
	elif isinstance(V1, list) and isinstance(V2, list):
		m = len(V2)
		return [vecadd(V1[i],V2[i%m]) for i in range(len(V1))]
	return None

def vecesc(A,V):
	if isinstance(V, tuple):
		return (A*V[0],A*V[1],A*V[2])
	elif isinstance(V, list):
		return [vecesc(A,T) for T in V]
	return None

def vecdot(V1,V2):
	if isinstance(V1, tuple) and isinstance(V2, tuple):
		return V1[0]*V2[0]+V1[1]*V2[1]+V1[2]*V2[2]
	elif isinstance(V1, list) and isinstance(V2, tuple):
		return [vecdot(T,V2) for T in V1]
	elif isinstance(V1, tuple) and isinstance(V2, list):
		return [vecdot(V1,T) for T in V2]
	elif isinstance(V1, list) and isinstance(V2, list):
		m = len(V2)
		return [vecdot(V1[i],V2[i%m]) for i in range(len(V1))]
	return None

def veccros(V1,V2):
	if isinstance(V1, tuple) and isinstance(V2, tuple):
		return (V1[1]*V2[2]-V1[2]*V2[1], V1[2]*V2[0]-V1[0]*V2[2], V1[0]*V2[1]-V1[1]*V2[0])
	elif isinstance(V1, list) and isinstance(V2, tuple):
		return [veccros(T,V2) for T in V1]
	elif isinstance(V1, tuple) and isinstance(V2, list):
		return [veccros(V1,T) for T in V2]
	elif isinstance(V1, list) and isinstance(V2, list):
		m = len(V2)
		return [veccros(V1[i],V2[i%m]) for i in range(len(V1))]
	return None

def vecmat(V,M):
	if isinstance(V, tuple):
		return (V[0]*M[0][0]+V[1]*M[0][1]+V[2]*M[0][2],V[0]*M[1][0]+V[1]*M[1][1]+V[2]*M[1][2],V[0]*M[2][0]+V[1]*M[2][1]+V[2]*M[2][2])
	elif isinstance(V, list):
		return [vecmat(T,M) for T in V]
	return None

def vecabs(V):
	if isinstance(V, tuple):
		return sqrt(vecdot(V,V))
	elif isinstance(V, list):
		return [vecabs(T) for T in V]
	return None

def vecdist(V1,V2):
	if isinstance(V1, tuple) and isinstance(V2, tuple):
		return vecabs(vecadd(vecesc(-1,V1),V2))
	elif isinstance(V1, list) and isinstance(V2, tuple):
		return [vecdist(T,V2) for T in V1]
	elif isinstance(V1, tuple) and isinstance(V2, list):
		return [vecdist(V1,T) for T in V2]
	elif isinstance(V1, list) and isinstance(V2, list):
		m = len(V2)
		return [vecdist(V1[i],V2[i%m]) for i in range(len(V1))]
	return None

def polar(V):
	#print('polar',V)
	if isinstance(V, tuple):
		R = sqrt(V[0]**2+V[1]**2+V[2]**2)
		r = sqrt(V[0]**2+V[1]**2)
		lat = atan2(V[2],r)
		lon = atan2(V[1],V[0])
		return (R,lon,lat)
	elif isinstance(V, list):
		return [polar(T) for T in V]
	return None

def coor(V):
	if isinstance(V, tuple):
		P = polar(V)
		return (180*P[2]/pi, 180*P[1]/pi)
	elif isinstance(V, list):
		return [coor(T) for T in V]
	return None

def coor_str(C):
	if isinstance(C, tuple):
		if len(C)==2:
			lat = '%.5f'%(abs(C[0]))
			lon = '%.5f'%(abs(C[1]))
			slt = C[0]<0 and 'S' or 'N'
			sln = C[0]<0 and 'E' or 'W'
			return lat+slt+', '+lon+sln
		else:
			return coor_str(coor(C))
	elif isinstance(C, list):
		return [coor_str(T) for T in C]
	return None

def coor_kml(C):
	if isinstance(C, tuple):
		if len(C)==2:
			return '%.5f,%.5f'%(C[1],C[0])
		else:
			return coor_kml(coor(C))
	elif isinstance(C, list):
		return ' '.join([coor_kml(T) for T in C])
	return None

def vecshift(V,n=1):
	if isinstance(V, list):
		m = len(V)
		return[V[(i+n)%m] for i in range(m)]
	return None

U0=(1,0,0)
U1=(0,1,0)
U2=(0,0,1)

if __name__ == '__main__':

	s = 1
	r = s/sin(pi/5)/2

	penta=[(r*cos(2*i*pi/5),r*sin(2*i*pi/5),0) for i in range(5)]

	h1 = sqrt(s**2-r**2)
	l = s/4/cos(pi/10)
	h2 = sqrt(s**2-l**2)
	h2=r
	H = 4*h1+3*h2

	X=2+cos(pi/5)
	M1=[[X,-sin(pi/5),0],[sin(pi/5),X,0],[0,0,0]]
	M2=[[X,sin(pi/5),0],[-sin(pi/5),X,0],[0,0,0]]
	p1=vecadd(penta, (0,0,1.5*h2+2*h1))
	p2=vecadd(vecesc(2,penta), (0,0,1.5*h2+h1))
	p3=vecadd(vecadd(vecesc(2,penta),vecshift(penta,-1)), (0,0,1.5*h2))
	p4=vecadd(vecadd(vecesc(2,penta),vecshift(penta,1)), (0,0,1.5*h2))
	p5=vecadd(vecmat(penta,M1),(0,0,.5*h2))
	p6=vecadd(vecmat(penta,M2),(0,0,.5*h2))

	if False:
		print(p5,p6)

		print('\n---- level 1:')
		print(vecabs(p1))
		print(coor_str(p1))

		print('\n---- level 2:')
		print(vecabs(p2))
		print(coor_str(p2))

		print('\n---- level 3:')
		print(vecabs(p3))
		print(coor_str(p3))
		print(vecabs(p4))
		print(coor_str(p4))

		print('\n---- level 4:')
		print(vecabs(p5))
		print(coor_str(p5))
		print(vecabs(p6))
		print(coor_str(p6))

		print()
		print(vecdist(p1,p2))
		print(vecdist(p2,p3))
		print(vecdist(p2,p4))
		print(vecdist(p3,p4))
		print(vecdist(p4,p5))
		print(vecdist(p3,p6))
		print(vecdist(p5,p6))

		print()
		print(vecdist(p1,vecshift(p1)))
		print(vecdist(p2,vecshift(p2)))
		print(vecdist(p4,vecshift(p3)))
		print(vecdist(p5,vecshift(p6)))

		print('\nHexa:')
		hexa= [p1[2],p1[3],p2[3],p3[3],p4[2],p2[2]]
		print(hexa)

	P = [[p3[i],p2[i],p4[i],p5[i],p6[i],p3[i]] for i in range(5)]
	P.append(p1)
	P[5].append(p1[0])

	print("""<?xml version="1.0" encoding="UTF-8"?>
	<kml xmlns="http://www.opengis.net/kml/2.2">
	  <Document>
		<name>football</name>""")

	Format="""      <Placemark>
			<name>%s</name>
			<Polygon>
			  <outerBoundaryIs>
				<LinearRing>
				  <coordinates>%s</coordinates>
				</LinearRing>
			  </outerBoundaryIs>%s
			</Polygon>
		  </Placemark>"""

	def hole(n):
		if(n==5):
			return """
			  <innerBoundaryIs>
				<LinearRing>
				  <coordinates>%s</coordinates>
				</LinearRing>
			  </innerBoundaryIs>"""%(coor_kml([(cos(i*pi/6),sin(i*pi/6),-tan(179*pi/360)) for i in range(13)]))
		return None

	for i in range(6):
		print(Format%('Penta_N%d'%(i), coor_kml(P[i]), '' ))
		print(Format%('Penta_S%d'%(i), coor_kml(vecesc(-1,P[i])), hole(i) ))

	Hx1 = [[p1[i],p1[(i+1)%5],p2[(i+1)%5],p3[(i+1)%5],p4[i],p2[i],p1[i]] for i in range(5)]
	Hx2 = [[p4[i],p5[i],vecesc(-1,p6[(i+3)%5]),vecesc(-1,p5[(i+3)%5]),p6[(i+1)%5],p3[(i+1)%5],p4[i]] for i in range(5)]
	for i in range(5):
		print(Format%('Hexa_N%d'%(i), coor_kml(Hx1[i]), '' ))
		print(Format%('Hexa_S%d'%(i), coor_kml(vecesc(-1,Hx1[i])), '' ))
		print(Format%('Hexa_Eq%d'%(2*i), coor_kml(Hx2[i]), '' ))
		print(Format%('Hexa_Eq%d'%(2*i+1), coor_kml(vecesc(-1,Hx2[i])), '' ))

	print("""    </Document>
	</kml>""");
