from math import sqrt

x = 1.1
y = -2.5
z = 3.14

print "Commutativity:" , (x*y == y*x)
print "Distributativity:" , ((x+y)*z == x*z + y*z)
print "Cancellation:" , (x*y) / y == x
print "Square root:" , (sqrt(x*x) == x) # (x > 0)