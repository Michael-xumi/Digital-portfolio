#include <Adafruit_NeoPixel.h>

//NEOPIXEL 
#define LED_PIN 12
#define LED_COUNT 4
Adafruit_NeoPixel pixels(LED_COUNT, LED_PIN, NEO_GRB + NEO_KHZ800);

unsigned long lastBlinkTime = 0;
bool blinkState = false;
#define BLINK_INTERVAL 300

//CHANGE 1: TURN FLAG 
bool isTurning = false;

//  MOTOR PINS 
#define MOTOR_LEFT_BACK  3
#define MOTOR_LEFT_FWD   5
#define MOTOR_RIGHT_BACK 11
#define MOTOR_RIGHT_FWD  6

//SERVO
#define SERVO_PIN 9
#define SERVO_OPEN_PULSE 1700
#define SERVO_CLOSE_PULSE 1000

//ULTRASONIC
#define TRIG_PIN 7
#define ECHO_PIN 8
#define FLAG_DISTANCE_THRESHOLD 12
#define OBSTACLE_DISTANCE 12

//SPEED
#define LEFT_SPEED 224
#define RIGHT_SPEED 200

//TIMINGS
#define DRIVE_TIME 1180
#define DRIVE_2_TIME 600
#define SERVO_TIME 1000
#define TURN_TIME 620

#define BACK_TIME 300 
#define BACK_TIME_2 800

//OBSTACLE TIMING
#define TURN90    680
#define SIDE_STEP 700
#define PASS_TIME 1650

//LINE SENSOR
const int SENSOR_PINS[8] = {A0,A1,A2,A3,A4,A5,A6,A7};
int sensorValues[8];
int weights[8] = {-3500,-2500,-1500,-500,500,1500,2500,3500};
#define BLACK_THRESHOLD 800

//END DETECTION
unsigned long blackStartTime = 0;
bool onBlack = false;
#define BLACK_TIME_THRESHOLD 180

//AVOID RECOVERY
unsigned long avoidEndTime = 0;
#define AVOID_IGNORE_TIME 800

//LINE MEMORY
int lastError = 0;

//STATE MACHINE
enum RobotState
{
  WAIT_FOR_FLAG,
  OPEN_1,
  DRIVE_1,
  GRAB_CONE,
  DRIVE_2,
  TURN_LEFT,
  LINE_FOLLOW,

  AVOID_TURN_RIGHT,
  AVOID_FORWARD_1,
  AVOID_TURN_LEFT_1,
  AVOID_FORWARD_2,
  AVOID_TURN_LEFT_2,
  AVOID_FORWARD_3,
  AVOID_TURN_RIGHT_2,

  STOPPED,
  DRIVE_BACKWARD,
  OPEN_2,
  DRIVE_BACKWARD_2
};

RobotState state = WAIT_FOR_FLAG;

//TIMERS
unsigned long stateTimer = 0;
unsigned long servoTimer = 0;

//FLAGS
bool hasReversed = false;

//SERVO
int servoPulse = SERVO_OPEN_PULSE;

//LED SYSTEM
void setColor(uint8_t r, uint8_t g, uint8_t b)
{
  for (int i = 0; i < LED_COUNT; i++)
    pixels.setPixelColor(i, pixels.Color(r, g, b));
  pixels.show();
}

void handleBlink(uint8_t r, uint8_t g, uint8_t b)
{
  if (millis() - lastBlinkTime > BLINK_INTERVAL)
  {
    lastBlinkTime = millis();
    blinkState = !blinkState;
  }

  if (blinkState) setColor(r,g,b);
  else setColor(0,0,0);
}

//ULTRASONIC
long readDistance()
{
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(5);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH, 30000);
  if (duration == 0) return -1;

  return duration * 0.034 / 2;
}

bool isFlagPresent()
{
  int detected = 0;
  for (int i = 0; i < 3; i++)
  {
    long d = readDistance();
    if (d > 0 && d <= FLAG_DISTANCE_THRESHOLD) detected++;
    delay(3);
  }
  return detected >= 2;
}

bool isObstacle()
{
  long d = readDistance();
  return (d > 0 && d < OBSTACLE_DISTANCE);
}

//SERVO
void updateServo()
{
  if (millis() > servoTimer)
  {
    servoTimer = millis() + 20;
    digitalWrite(SERVO_PIN, HIGH);
    delayMicroseconds(servoPulse);
    digitalWrite(SERVO_PIN, LOW);
  }
}

void openGripper()
{
  servoPulse = SERVO_OPEN_PULSE;
  if (!isTurning) setColor(0,255,0);
}

void closeGripper()
{
  servoPulse = SERVO_CLOSE_PULSE;
}

//MOTOR
void driveForward()
{
  if (!isTurning) setColor(255,255,255);

  analogWrite(MOTOR_LEFT_FWD, LEFT_SPEED);
  analogWrite(MOTOR_LEFT_BACK, 0);
  analogWrite(MOTOR_RIGHT_FWD, RIGHT_SPEED);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}

void driveBackward()
{
  if (!isTurning) setColor(255,255,255);

  analogWrite(MOTOR_LEFT_FWD, 0);
  analogWrite(MOTOR_LEFT_BACK, LEFT_SPEED);
  analogWrite(MOTOR_RIGHT_FWD, 0);
  analogWrite(MOTOR_RIGHT_BACK, RIGHT_SPEED);
}

//CHANGE 2
void pivotRight()
{
  isTurning = true;
  handleBlink(255,165,0);

  analogWrite(MOTOR_LEFT_FWD, LEFT_SPEED);
  analogWrite(MOTOR_LEFT_BACK, 0);
  analogWrite(MOTOR_RIGHT_FWD, 0);
  analogWrite(MOTOR_RIGHT_BACK, RIGHT_SPEED);
}

void pivotLeft()
{
  isTurning = true;
  handleBlink(255,165,0);

  analogWrite(MOTOR_LEFT_FWD, 0);
  analogWrite(MOTOR_LEFT_BACK, LEFT_SPEED);
  analogWrite(MOTOR_RIGHT_FWD, RIGHT_SPEED);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}

void stopMotors()
{
  if (!isTurning) setColor(255,0,0);

  analogWrite(MOTOR_LEFT_FWD, 0);
  analogWrite(MOTOR_LEFT_BACK, 0);
  analogWrite(MOTOR_RIGHT_FWD, 0);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}

//LINE FOLLOW
void lineFollow()
{
  if (!isTurning) setColor(255,255,255);

  if (millis() - avoidEndTime > AVOID_IGNORE_TIME)
  {
    if (isObstacle())
    {
      stopMotors();
      state = AVOID_TURN_RIGHT;
      stateTimer = millis();
      onBlack = false;
      return;
    }
  }

  long position = 0;
  int total = 0;

  for (int i = 0; i < 8; i++)
  {
    sensorValues[i] = analogRead(SENSOR_PINS[i]);
    if (sensorValues[i] > BLACK_THRESHOLD)
    {
      position += weights[i];
      total++;
    }
  }

  int blackSensors = 0;
  for (int i = 0; i < 8; i++)
    if (sensorValues[i] > BLACK_THRESHOLD) blackSensors++;

  if (blackSensors == 8)
  {
    if (!onBlack)
    {
      onBlack = true;
      blackStartTime = millis();
    }

    if (millis() - blackStartTime > BLACK_TIME_THRESHOLD)
    {
      stopMotors();
      state = STOPPED;
      stateTimer = millis();
      return;
    }
  }
  else
  {
    onBlack = false;
  }

  if (total == 0)
  {
    if (lastError < 0) pivotLeft();
    else pivotRight();
    return;
  }

  int error = position / total;
  lastError = error;

  int baseSpeed = (abs(error) < 400) ? 255 : (abs(error) < 1200 ? 200 : 150);
  int correction = error / 16;

  int leftSpeed = constrain(baseSpeed - correction, 0, 255);
  int rightSpeed = constrain(baseSpeed + correction, 0, 255);

  analogWrite(MOTOR_LEFT_FWD, leftSpeed);
  analogWrite(MOTOR_RIGHT_FWD, rightSpeed);
  analogWrite(MOTOR_LEFT_BACK, 0);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}

//SETUP
void setup()
{
  pinMode(MOTOR_LEFT_BACK, OUTPUT);
  pinMode(MOTOR_LEFT_FWD, OUTPUT);
  pinMode(MOTOR_RIGHT_BACK, OUTPUT);
  pinMode(MOTOR_RIGHT_FWD, OUTPUT);

  digitalWrite(MOTOR_LEFT_BACK, LOW);
  digitalWrite(MOTOR_LEFT_FWD, LOW);
  digitalWrite(MOTOR_RIGHT_BACK, LOW);
  digitalWrite(MOTOR_RIGHT_FWD, LOW);

  pinMode(SERVO_PIN, OUTPUT);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);

  pixels.begin();
  pixels.clear();
  pixels.show();

  stopMotors();
}

//LOOP
void loop()
{
  unsigned long now = millis();

  //CHANGE 3
  isTurning = false;

  updateServo();

  switch (state)
  {
    case WAIT_FOR_FLAG:
      if (!isFlagPresent())
      {
        stateTimer = now;
        state = OPEN_1;
      }
      break;

    case OPEN_1:
      openGripper();
      if (now - stateTimer > SERVO_TIME)
      {
        stateTimer = now;
        state = DRIVE_1;
      }
      break;

    case DRIVE_1:
      driveForward();
      if (now - stateTimer > DRIVE_TIME)
      {
        stopMotors();
        stateTimer = now;
        state = GRAB_CONE;
      }
      break;

    case GRAB_CONE:
      closeGripper();
      if (now - stateTimer > SERVO_TIME)
      {
        stateTimer = now;
        state = DRIVE_2;
      }
      break;

    case DRIVE_2:
      driveForward();
      if (now - stateTimer > DRIVE_2_TIME)
      {
        stopMotors();
        stateTimer = now;
        state = TURN_LEFT;
      }
      break;

    case TURN_LEFT:
      pivotLeft();
      if (now - stateTimer > TURN_TIME)
      {
        stopMotors();
        state = LINE_FOLLOW;
      }
      break;

    case LINE_FOLLOW:
      lineFollow();
      break;

    //AVOID STATES
    case AVOID_TURN_RIGHT:
      pivotRight();
      if (now - stateTimer > TURN90)
      {
        stateTimer = now;
        state = AVOID_FORWARD_1;
      }
      break;

    case AVOID_FORWARD_1:
      driveForward();
      if (now - stateTimer > SIDE_STEP)
      {
        stateTimer = now;
        state = AVOID_TURN_LEFT_1;
      }
      break;

    case AVOID_TURN_LEFT_1:
      pivotLeft();
      if (now - stateTimer > TURN90)
      {
        stateTimer = now;
        state = AVOID_FORWARD_2;
      }
      break;

    case AVOID_FORWARD_2:
      driveForward();
      if (now - stateTimer > PASS_TIME)
      {
        stateTimer = now;
        state = AVOID_TURN_LEFT_2;
      }
      break;

    case AVOID_TURN_LEFT_2:
      pivotLeft();
      if (now - stateTimer > TURN90)
      {
        stateTimer = now;
        state = AVOID_FORWARD_3;
      }
      break;

    case AVOID_FORWARD_3:
      driveForward();
      if (now - stateTimer > SIDE_STEP)
      {
        stateTimer = now;
        state = AVOID_TURN_RIGHT_2;
      }
      break;

    case AVOID_TURN_RIGHT_2:
      pivotRight();
      if (now - stateTimer > TURN90)
      {
        avoidEndTime = now;
        state = LINE_FOLLOW;
      }
      break;

    case STOPPED:
      stopMotors();
      if (!hasReversed && now - stateTimer > 500)
      {
        hasReversed = true;
        stateTimer = now;
        state = DRIVE_BACKWARD;
      }
      break;

    case DRIVE_BACKWARD:
      driveBackward();
      if (now - stateTimer > BACK_TIME)
      {
        stopMotors();
        stateTimer = now;
        state = OPEN_2;
      }
      break;

    case OPEN_2:
      openGripper();
      if (now - stateTimer > SERVO_TIME)
      {
        stateTimer = now;
        state = DRIVE_BACKWARD_2;
      }
      break;

    case DRIVE_BACKWARD_2:
      driveBackward();
      if (now - stateTimer > BACK_TIME_2)
      {
        stopMotors();
        state = STOPPED;
      }
      break;
  }
}
