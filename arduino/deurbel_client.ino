#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// Voor ESP8266 gebruik <ESP8266WiFi.h> en pas pin mapping aan.
const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASS = "YOUR_WIFI_PASSWORD";
const char* API_BASE = "https://jouwdomein.nl/api";
const char* DEVICE_KEY = "change_this_device_key";

const int RELAY_PIN = 5;
unsigned long lastPollMs = 0;
const unsigned long POLL_INTERVAL_MS = 1500;

void connectWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  while (WiFi.status() != WL_CONNECTED) {
    delay(400);
  }
}

bool pollCommand(int &commandId, String &token, int &pulseMs) {
  HTTPClient http;
  String url = String(API_BASE) + "/device_poll.php";

  http.begin(url);
  http.addHeader("X-DEVICE-KEY", DEVICE_KEY);

  int code = http.GET();
  if (code != 200) {
    http.end();
    return false;
  }

  String body = http.getString();
  http.end();

  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, body);
  if (err) {
    return false;
  }

  bool hasCommand = doc["has_command"] | false;
  if (!hasCommand) {
    return false;
  }

  commandId = doc["command"]["id"] | 0;
  token = String((const char*)doc["command"]["token"]);
  pulseMs = doc["command"]["pulse_ms"] | 1200;

  return commandId > 0 && token.length() > 10;
}

bool ackCommand(int commandId, const String &token) {
  HTTPClient http;
  String url = String(API_BASE) + "/device_ack.php";

  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-DEVICE-KEY", DEVICE_KEY);

  StaticJsonDocument<256> doc;
  doc["command_id"] = commandId;
  doc["token"] = token;

  String payload;
  serializeJson(doc, payload);

  int code = http.POST(payload);
  http.end();

  return code == 200;
}

void pulseRelay(int pulseMs) {
  digitalWrite(RELAY_PIN, HIGH);
  delay(pulseMs);
  digitalWrite(RELAY_PIN, LOW);
}

void setup() {
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  connectWifi();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWifi();
  }

  unsigned long now = millis();
  if (now - lastPollMs < POLL_INTERVAL_MS) {
    delay(40);
    return;
  }

  lastPollMs = now;

  int commandId = 0;
  int pulseMs = 1200;
  String token = "";

  if (pollCommand(commandId, token, pulseMs)) {
    pulseRelay(pulseMs);
    ackCommand(commandId, token);
  }
}
