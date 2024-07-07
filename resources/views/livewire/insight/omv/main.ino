const int sensorPin = A0;  // Analog pin for sensor input
const long interval = 1000;  // Interval between readings (1 second)
unsigned long previousMillis = 0;

void setup() {
  Serial.begin(9600);  // Initialize serial communication at 9600 baud
}

void loop() {
  unsigned long currentMillis = millis();

  // Check if it's time to take a reading
  if (currentMillis - previousMillis >= interval) {
    previousMillis = currentMillis;

    // Read the sensor value
    int sensorValue = analogRead(sensorPin);

    // Map the sensor value to a 4-digit number (0000-9999)
    int mappedValue = map(sensorValue, 0, 1023, 0, 9999);

    // Ensure the value is always 4 digits
    if (mappedValue < 1000) {
      Serial.print("0");
    }
    if (mappedValue < 100) {
      Serial.print("0");
    }
    if (mappedValue < 10) {
      Serial.print("0");
    }

    // Send the value
    Serial.println(mappedValue);
  }
}
