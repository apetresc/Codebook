#include <math.h>
#include <stdio.h>

int main() {
	float x = 1.1;
	float y = -2.5;
	float z = 3.14;

	printf("Commutativity: %d\n", (x*y == y*x));
	printf("Distributativity: %d\n", ((x+y)*z == x*z + y*z));
	printf("Cancellation: %d\n", ((x*y) / y == x));
	printf("Square root %d\n", (sqrt(x*x) == x));

	return 0;
}
