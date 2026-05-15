// Line Following  Success 13 March 2026

#define SENSOR_PINS {A0,A1,A2,A3,A4,A5,A6,A7}
int sensorPins[8] = SENSOR_PINS;

int sensorValues[8];

#define WEIGHTS {-3500,-2500,-1500,-500,500,1500,2500,3500}
int weights[8] = WEIGHTS;

// MOTOR CONTROL 
#define MOTOR_LEFT_FORWARD 5
#define MOTOR_LEFT_BACK 3
#define MOTOR_RIGHT_BACK 11
#define MOTOR_RIGHT_FORWARD 6

#define BLACK_THRESHOLD 800

void setup()
{
    Serial.begin(9600);

    pinMode(MOTOR_LEFT_FORWARD, OUTPUT);
    pinMode(MOTOR_LEFT_BACK, OUTPUT);
    pinMode(MOTOR_RIGHT_FORWARD, OUTPUT);
    pinMode(MOTOR_RIGHT_BACK, OUTPUT);

    digitalWrite(MOTOR_LEFT_FORWARD, LOW);
    digitalWrite(MOTOR_LEFT_BACK, LOW);
    digitalWrite(MOTOR_RIGHT_FORWARD, LOW);
    digitalWrite(MOTOR_RIGHT_BACK, LOW);
}

void loop()
{
    long position = 0;
    int total = 0;

    for (int i = 0; i < 8; i++)
    {
        sensorValues[i] = analogRead(sensorPins[i]);

        if (sensorValues[i] > BLACK_THRESHOLD)
        {
            position += weights[i];
            total++;
        }
    }

    if (total == 0)
    {
        stopMotors();
        return;
    }

    int error = position / total;

    int baseSpeed;

    if (abs(error) < 400)
    {
        baseSpeed = 255;
    }
    else if (abs(error) < 1200)
    {
        baseSpeed = 200;
    }
    else
    {
        baseSpeed = 150;
    }

    int correction = error / 16;

    //  CURVE ANTICIPATION 
    if (sensorValues[0] > BLACK_THRESHOLD || sensorValues[1] > BLACK_THRESHOLD)
    {
        correction -= 25;
    }

    if (sensorValues[6] > BLACK_THRESHOLD || sensorValues[7] > BLACK_THRESHOLD)
    {
        correction += 25;
    }

    int leftSpeed = baseSpeed - correction;
    int rightSpeed = baseSpeed + correction;

    leftSpeed = constrain(leftSpeed, 0, 255);
    rightSpeed = constrain(rightSpeed, 0, 255);

    analogWrite(MOTOR_LEFT_FORWARD, leftSpeed);
    analogWrite(MOTOR_LEFT_BACK, 0);

    analogWrite(MOTOR_RIGHT_FORWARD, rightSpeed);
    analogWrite(MOTOR_RIGHT_BACK, 0);
}

void stopMotors()
{
    analogWrite(MOTOR_LEFT_FORWARD, 0);
    analogWrite(MOTOR_LEFT_BACK, 0);
    analogWrite(MOTOR_RIGHT_FORWARD, 0);
    analogWrite(MOTOR_RIGHT_BACK, 0);
}
