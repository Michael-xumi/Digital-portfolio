//  MOTOR PINS
#define LEFT_BACKWARD  3
#define LEFT_FORWARD   5
#define RIGHT_BACKWARD 11
#define RIGHT_FORWARD  6

//ULTRASONIC SENSOR PINS
#define TRIG_PIN 7
#define ECHO_PIN 8

//  SPEED SETTINGS
#define FAST 230
#define SLOW 80

//  TIMING
#define TURN90    600
#define SIDE_STEP 800
#define PASS_TIME 1500

//  SETUP
void setup() {
  // Motor pins
  pinMode(LEFT_FORWARD, OUTPUT);
  pinMode(LEFT_BACKWARD, OUTPUT);
  pinMode(RIGHT_FORWARD, OUTPUT);
  pinMode(RIGHT_BACKWARD, OUTPUT);

  digitalWrite(LEFT_FORWARD, LOW);
  digitalWrite(LEFT_BACKWARD, LOW);
  digitalWrite(RIGHT_FORWARD, LOW);
  digitalWrite(RIGHT_BACKWARD, LOW);

  // Ultrasonic pins
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);

  stopMotors();
}

// LOOP
void loop() {
  long distance = getDistance();

  if (distance > 0 && distance < 20) {
    // Object detected → perform detour
    avoidObstacle();

    // Wait until object is cleared to prevent re-trigger
    while (getDistance() < 20) {
      forward();
    }
  } else {
    forward();
  }
}

// MOTOR FUNCTIONS
void stopMotors() {
  analogWrite(LEFT_FORWARD, 0);
  analogWrite(LEFT_BACKWARD, 0);
  analogWrite(RIGHT_FORWARD, 0);
  analogWrite(RIGHT_BACKWARD, 0);
}

void forward() {
  analogWrite(LEFT_FORWARD, FAST);
  analogWrite(LEFT_BACKWARD, 0);
  analogWrite(RIGHT_FORWARD, FAST);
  analogWrite(RIGHT_BACKWARD, 0);
}

void backward() {
  analogWrite(LEFT_FORWARD, 0);
  analogWrite(LEFT_BACKWARD, FAST);
  analogWrite(RIGHT_FORWARD, 0);
  analogWrite(RIGHT_BACKWARD, FAST);
}

// Pivot turns
void pivotRight() {
  analogWrite(LEFT_FORWARD, FAST);
  analogWrite(LEFT_BACKWARD, 0);
  analogWrite(RIGHT_FORWARD, 0);
  analogWrite(RIGHT_BACKWARD, FAST);
}

void pivotLeft() {
  analogWrite(LEFT_FORWARD, 0);
  analogWrite(LEFT_BACKWARD, FAST);
  analogWrite(RIGHT_FORWARD, FAST);
  analogWrite(RIGHT_BACKWARD, 0);
}

// ULTRASONIC FUNCTION
long getDistance() {
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);

  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH, 30000);
  if (duration == 0) return 400;

  return duration * 0.034 / 2;
}

//  OBSTACLE DETOUR FUNCTION
void avoidObstacle() {
  stopMotors();
  delay(300);

  // Turn right 90°
  pivotRight();
  delay(TURN90);
  stopMotors();
  delay(300);

  // Move sideways
  forward();
  delay(SIDE_STEP);
  stopMotors();
  delay(300);

  // Turn left 90°
  pivotLeft();
  delay(TURN90);
  stopMotors();
  delay(300);

  // Move forward past the object
  forward();
  delay(PASS_TIME);
  stopMotors();
  delay(300);

  //Turn left 90°
  pivotLeft();
  delay(TURN90);
  stopMotors();
  delay(300);

  // Move sideways same distance to return to original line
  forward();
  delay(SIDE_STEP);
  stopMotors();
  delay(300);

  // Turn right 90° to face original direction
  pivotRight();
  delay(TURN90);
  stopMotors();
  delay(300);
}
