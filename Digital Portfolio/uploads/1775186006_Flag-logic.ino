//= MOTOR PINS
#define MOTOR_LEFT_BACK  3
#define MOTOR_LEFT_FWD   5
#define MOTOR_RIGHT_BACK 11
#define MOTOR_RIGHT_FWD  6

// SERVO 
#define SERVO_PIN 9
#define SERVO_OPEN_PULSE 1700
#define SERVO_CLOSE_PULSE 1000

//  SPEED 
#define LEFT_SPEED 224
#define RIGHT_SPEED 200

//  TIMINGS
#define DRIVE_TIME 1300
#define DRIVE_2_TIME 600
#define SERVO_TIME 1000
#define TURN_TIME 620

//  ULTRASONIC 
#define TRIG_PIN 7
#define ECHO_PIN 8
#define FLAG_DISTANCE_THRESHOLD 12   

//  STATE MACHINE
enum RobotState
{
  OPEN_1,
  DRIVE_1,
  GRAB_CONE,
  DRIVE_2,
  TURN_LEFT,
  STOPPED
};

RobotState state = OPEN_1;

//  TIMERS
unsigned long stateTimer = 0;
unsigned long servoTimer = 0;

//  SERVO 
int servoPulse = SERVO_OPEN_PULSE;


// = SETUP 

void setup()
{
  pinMode(MOTOR_LEFT_BACK, OUTPUT);
  pinMode(MOTOR_LEFT_FWD, OUTPUT);
  pinMode(MOTOR_RIGHT_BACK, OUTPUT);
  pinMode(MOTOR_RIGHT_FWD, OUTPUT);
  
  pinMode(SERVO_PIN, OUTPUT);

  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);

  digitalWrite(TRIG_PIN, LOW);

  stateTimer = millis();
}


// LOOP 

void loop()
{
  unsigned long now = millis();

  updateServo();


  if ((state == DRIVE_1 || state == DRIVE_2) && isFlagPresent())
  {
    stopMotors();
    stateTimer = now;
    return;
  }

  switch (state)
  {
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
        delay(100);
        stateTimer = millis();
        state = TURN_LEFT;
      }
      break;

    case TURN_LEFT:
      turnLeft();
      if (now - stateTimer > TURN_TIME)
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



// FUNCTIONS 


// ULTRASONIC 
long readDistance()
{
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(5);

  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH, 30000); // longer timeout

  if (duration == 0)
  {
    return -1;
  }

  return duration * 0.034 / 2;
}

bool isFlagPresent()
{
  int detected = 0;

  for (int i = 0; i < 3; i++)
  {
    long d = readDistance();

    if (d > 0 && d <= FLAG_DISTANCE_THRESHOLD)
    {
      detected++;
    }

    delay(3);
  }

  return (detected >= 2);
}


//  SERVO 
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


//  MOTORS
void driveForward()
{
  analogWrite(MOTOR_LEFT_FWD, LEFT_SPEED);
  analogWrite(MOTOR_LEFT_BACK, 0);

  analogWrite(MOTOR_RIGHT_FWD, RIGHT_SPEED);  // ✅ fixed
  analogWrite(MOTOR_RIGHT_BACK, 0);
}

void turnLeft()
{
  analogWrite(MOTOR_LEFT_FWD, 0);
  analogWrite(MOTOR_LEFT_BACK, LEFT_SPEED);

  analogWrite(MOTOR_RIGHT_FWD, RIGHT_SPEED);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}

void stopMotors()
{
  analogWrite(MOTOR_LEFT_FWD, 0);
  analogWrite(MOTOR_LEFT_BACK, 0);

  analogWrite(MOTOR_RIGHT_FWD, 0);
  analogWrite(MOTOR_RIGHT_BACK, 0);
}
