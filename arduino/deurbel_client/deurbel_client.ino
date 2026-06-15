#include <WiFiNINA.h>
#include <ArduinoHttpClient.h>
#include <ArduinoJson.h>
#include <LiquidCrystal.h>

// UNO WiFi Rev2 instellingen
const char* WIFI_SSID = "SerialWLAN";
const char* WIFI_PASS = "kensentme01!";
const char* API_HOST = "awesome-robinson.149-210-167-40.plesk.page";
const int API_PORT = 443;
const char* API_BASE_PATH = "/api";
const char* DEVICE_KEY = "8921570317:AAGQqk8IRbuEmS76gmE3xkTcySoOvSbuhA8";

const int RELAY_PIN = 5;
// Parallel LCD pins (pas aan naar jouw bedrading)
const int LCD_RS = 7;
const int LCD_EN = 8;
const int LCD_D4 = 9;
const int LCD_D5 = 10;
const int LCD_D6 = 11;
const int LCD_D7 = 12;

unsigned long lastPollMs = 0;
const unsigned long POLL_INTERVAL_MS = 1500;

WiFiSSLClient sslClient;
LiquidCrystal lcd(LCD_RS, LCD_EN, LCD_D4, LCD_D5, LCD_D6, LCD_D7);

String ipToString(const IPAddress &ip) {
  char buf[20];
  snprintf(buf, sizeof(buf), "%u.%u.%u.%u", ip[0], ip[1], ip[2], ip[3]);
  return String(buf);
}

void lcdStatus(const String &line1, const String &line2 = "") {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1.substring(0, 16));
  lcd.setCursor(0, 1);
  lcd.print(line2.substring(0, 16));
}

void printWifiStatus() {
  Serial.print("WiFi status: ");
  Serial.println((int)WiFi.status());
  Serial.print("SSID: ");
  Serial.println(WiFi.SSID());
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
  Serial.print("RSSI: ");
  Serial.println(WiFi.RSSI());

  IPAddress hostIp;
  if (WiFi.hostByName(API_HOST, hostIp)) {
    Serial.print("DNS ");
    Serial.print(API_HOST);
    Serial.print(" -> ");
    Serial.println(hostIp);
  } else {
    Serial.print("DNS lookup failed for ");
    Serial.println(API_HOST);
  }
}

void connectWifi() {
  lcdStatus("WiFi verbinden", WIFI_SSID);
  Serial.print("Connecting to WiFi");
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  while (WiFi.status() != WL_CONNECTED) {
    Serial.print(".");
    delay(400);
  }

  Serial.println();
  Serial.print("WiFi connected. IP: ");
  Serial.println(WiFi.localIP());
  lcdStatus("WiFi OK", ipToString(WiFi.localIP()));
  printWifiStatus();
}

bool pollCommand(int &commandId, String &token, int &pulseMs) {
  HttpClient http(sslClient, API_HOST, API_PORT);
  String path = String(API_BASE_PATH) + "/device_poll.php";

  lcdStatus("Poll API", API_HOST);
  Serial.print("Polling: https://");
  Serial.print(API_HOST);
  Serial.print(path);
  Serial.println();

  http.beginRequest();
  http.get(path);
  http.sendHeader("X-DEVICE-KEY", DEVICE_KEY);
  http.endRequest();

  int code = http.responseStatusCode();
  if (code != 200) {
    String errorBody = http.responseBody();
    http.stop();
    Serial.print("poll status: ");
    Serial.println(code);
    lcdStatus("Poll fout", String(code));
    if (errorBody.length() > 0) {
      Serial.print("poll body: ");
      Serial.println(errorBody);
    }
    return false;
  }

  String body = http.responseBody();
  http.stop();

  Serial.print("poll status: ");
  Serial.println(code);
  Serial.print("poll body: ");
  Serial.println(body);
  lcdStatus("Poll OK", "Geen opdracht");

  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, body);
  if (err) {
    Serial.print("JSON parse error: ");
    Serial.println(err.c_str());
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
  HttpClient http(sslClient, API_HOST, API_PORT);
  String path = String(API_BASE_PATH) + "/device_ack.php";

  StaticJsonDocument<256> doc;
  doc["command_id"] = commandId;
  doc["token"] = token;

  String payload;
  serializeJson(doc, payload);

  http.beginRequest();
  http.post(path);
  http.sendHeader("Content-Type", "application/json");
  http.sendHeader("X-DEVICE-KEY", DEVICE_KEY);
  http.sendHeader("Content-Length", payload.length());
  http.beginBody();
  http.print(payload);
  http.endRequest();

  int code = http.responseStatusCode();
  http.stop();

  Serial.print("ack status: ");
  Serial.println(code);
  lcdStatus("ACK status", String(code));

  return code == 200;
}

void pulseRelay(int pulseMs) {
  Serial.println("Relay pulse start");
  lcdStatus("Deur openen", String(pulseMs) + "ms");
  digitalWrite(RELAY_PIN, HIGH);
  delay(pulseMs);
  digitalWrite(RELAY_PIN, LOW);
  Serial.println("Relay pulse done");
  lcdStatus("Deur geopend", "Klaar");
}

void setup() {
  Serial.begin(115200);
  while (!Serial) {
    ;
  }

  Serial.println("Doorbell client starting...");
  lcd.begin(16, 2);
  lcdStatus("Deurbel client", "Opstarten...");

  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  connectWifi();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected, reconnecting...");
    lcdStatus("WiFi weg", "Reconnect...");
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

  Serial.println("poll tick");
  lcdStatus("Wachten...", "Nieuwe poll");

  if (pollCommand(commandId, token, pulseMs)) {
    Serial.print("Command received: ");
    Serial.println(commandId);
    lcdStatus("Opdracht", String(commandId));
    pulseRelay(pulseMs);
    ackCommand(commandId, token);
  }
}
