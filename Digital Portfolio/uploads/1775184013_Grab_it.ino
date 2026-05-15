// MOTOR PINS 
#define MOTOR_LEFT_BACK  3
#define MOTOR_LEFT_FWD   5
#define MOTOR_RIGHT_BACK 6
#define MOTOR_RIGHT_FWD  11

//  SERVO 
#define SERVO_PIN 9
#define SERVO_OPEN_PULSE 1700
#define SERVO_CLOSE_PULSE 1000

// SPEED 
#define LEFT_SPEED 250
#define RIGHT_SPEED 185

// TIMINGS 
#define DRIVE_TIME 1300
#define SERVO_TIME 1000

//  STATE MACHINE
enum RobotState
{
  OPEN_1,
  CLOSE_1,
  OPEN_2,
  DRIVE_1,
  GRAB_CONE,
  DRIVE_2,
  STOPPED
};

RobotState state = OPEN_1;

//  TIMERS
unsigned long stateTimer = 0;
unsigned long servoTimer = 0;

//  SERVO 
int servoPulse = SERVO_OPEN_PULSE;


//  SERVO SIGNAL
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
}

void closeGripper()
{
  servoPulse = SERVO_CLOSE_PULSE;
}


//  MOTOR CONTROL 
void driveForward()
{
  analogWrite(MOTOR_LEFT_FWD, LEFT_SPEED);
  analogWrite(MOTOR_LEFT_BACK, 0);

  analogWrite(MOTOR_RIGHT_FWD, RIGHT_SPEED);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}

void stopMotors() {
  analogWrite(MOTOR_LEFT_FWD, 0);
  analogWrite(MOTOR_LEFT_BACK, 0);

  analogWrite(MOTOR_RIGHT_FWD, 0);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}


//  SETUP 
void setup() {
  pinMode(MOTOR_LEFT_BACK, OUTPUT);
  pinMode(MOTOR_LEFT_FWD, OUTPUT);
  pinMode(MOTOR_RIGHT_BACK, OUTPUT);
  pinMode(MOTOR_RIGHT_FWD, OUTPUT);

  digitalWrite(MOTOR_LEFT_BACK, LOW);
  digitalWrite(MOTOR_LEFT_FWD, LOW);
  digitalWrite(MOTOR_RIGHT_BACK, LOW);
  digitalWrite(MOTOR_RIGHT_FWD, LOW);

  pinMode(SERVO_PIN, OUTPUT);

  stateTimer = millis();
}


// LOOP
void loop()
{
  unsigned long now = millis();

  updateServo();

  switch (state) {

    case OPEN_1:
      openGripper();
      if (now - stateTimer > SERVO_TIME) {
        stateTimer = now;
        state = CLOSE_1;
      }
      break;


    case CLOSE_1:
      closeGripper();
      if (now - stateTimer > SERVO_TIME) {
        stateTimer = now;
        state = OPEN_2;
      }
      break;


    case OPEN_2:
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
      if (now - stateTimer > DRIVE_TIME)
      {
        stopMotors();
        state = STOPPED;
      }
      break;


    case STOPPED:
      stopMotors();
      break;
  }
}
