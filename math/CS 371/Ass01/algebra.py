from math import sqrt
from random import random
from random import randint

commutativity = distributativity = cancellation = squareroot = True

for i in range(500):
	x = random() * randint(1, 1000000)
	y = random() * randint(1, 1000000)
	z = random() * randint(1, 1000000)
	if x * y != y * x:
		commutativity = False
	if (x + y) * z != x * z + y * z:
		distributativity = False
	if (x * y) / y != x:
		cancellation = False
	if sqrt(x * x) != x:
		squareroot = False

print "Commutativity:" , commutativity
print "Distributativity:" , distributativity
print "Cancellation:" , cancellation
print "Square root:" , squareroot