#include <WiFiNINA.h>
#include <ArduinoHttpClient.h>
#include <ArduinoJson.h>
#include <LiquidCrystal.h>
#include <avr/wdt.h>

// UNO WiFi Rev2 instellingen
const char* WIFI_SSID = "DeurbelZV";
const char* WIFI_PASS = "Zilvervloot987!!";
const char* API_HOST = "zilvervlootbel.nl";
const int API_PORT = 443;
const char* API_BASE_PATH = "/api";
const char* DEVICE_KEY = "Quaf-AT_SIGN-slyp-Cact-FIV";

const int RELAY_PIN = 3;
const int RELAY_PULSE_MS = 3000;
const unsigned long RELAY_SAFETY_REFRESH_MS = 5000;
const int RELAY_ACTIVE_STATE = LOW;
const int RELAY_INACTIVE_STATE = HIGH;
const int STATUS_LED_PIN = LED_BUILTIN;
// Parallel LCD pins (pas aan naar jouw bedrading)
const int LCD_RS = 8;
const int LCD_EN = 9;
const int LCD_D4 = 4;
const int LCD_D5 = 5;
const int LCD_D6 = 6;
const int LCD_D7 = 7;

unsigned long lastPollMs = 0;
const unsigned long POLL_INTERVAL_MS = 4000;
unsigned long lastPollSuccessMs = 0;
int consecutivePollFailures = 0;
const int MAX_POLL_FAILURES_BEFORE_RECONNECT = 20;
const unsigned long MAX_POLL_STALE_MS = 180000;
const unsigned long WIFI_CONNECT_TIMEOUT_MS = 45000;
int watchdogReconnectCount = 0;
unsigned long lastLcdRotateMs = 0;
const unsigned long LCD_ROTATE_INTERVAL_MS = 5000;
int lcdPage = 0;
unsigned long lcdTransientUntilMs = 0;
unsigned long lastRelaySafetyMs = 0;

bool relayActive = false;
unsigned long relayOffAtMs = 0;
bool relayFinishedEvent = false;

bool pendingAck = false;
int pendingAckCommandId = 0;
char pendingAckToken[96] = "";
char pendingAckLabel[24] = "";
unsigned long lastAckAttemptMs = 0;
const unsigned long ACK_RETRY_INTERVAL_MS = 5000;

WiFiSSLClient sslClient;
LiquidCrystal lcd(LCD_RS, LCD_EN, LCD_D4, LCD_D5, LCD_D6, LCD_D7);

char apiStatus[16] = "init";
char apiMessage[24] = "startup";
unsigned long apiTs = 0;

char lastCommand[24] = "none";
char lastCommandStatus[24] = "idle";
char lastCommandError[40] = "";
unsigned long lastCommandTs = 0;

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

void lcdTransient(const String &line1, const String &line2 = "", unsigned long durationMs = 1500) {
  lcdStatus(line1, line2);
  lcdTransientUntilMs = millis() + durationMs;
}

String uptimeTs() {
  unsigned long total = millis() / 1000;
  unsigned long h = total / 3600;
  unsigned long m = (total % 3600) / 60;
  unsigned long s = total % 60;
  char buf[16];
  snprintf(buf, sizeof(buf), "%02lu:%02lu:%02lu", h, m, s);
  return String(buf);
}

void setApiStatus(const String &status, const String &message) {
  status.toCharArray(apiStatus, sizeof(apiStatus));
  message.toCharArray(apiMessage, sizeof(apiMessage));
  apiTs = millis();
}

void setLastCommand(const String &command, const String &status, const String &errorMsg) {
  command.toCharArray(lastCommand, sizeof(lastCommand));
  status.toCharArray(lastCommandStatus, sizeof(lastCommandStatus));
  errorMsg.toCharArray(lastCommandError, sizeof(lastCommandError));
  lastCommandTs = millis();
}

void printRuntimeStatus() {
  Serial.print("API Status: ");
  Serial.print(apiStatus);
  Serial.print("/");
  Serial.print(apiMessage);
  Serial.print(" (");
  Serial.print(uptimeTs());
  Serial.println(")");

  Serial.print("Last command: ");
  Serial.print(lastCommand);
  Serial.print("/");
  Serial.print(lastCommandStatus);
  if (lastCommandError[0] != '\0') {
    Serial.print("/");
    Serial.print(lastCommandError);
  }
  Serial.print(" (");
  unsigned long t = lastCommandTs > 0 ? lastCommandTs : millis();
  unsigned long total = t / 1000;
  char buf[16];
  snprintf(buf, sizeof(buf), "%02lu:%02lu:%02lu", total / 3600, (total % 3600) / 60, total % 60);
  Serial.print(buf);
  Serial.println(")");
}

void renderSummaryLcd() {
  if (lcdPage == 0) {
    lcdStatus(String("API: ") + String(apiStatus), String(apiMessage));
  } else if (lcdPage == 1) {
    String line1 = String("CMD:") + String(lastCommand);
    String line2 = String(lastCommandStatus);
    if (lastCommandError[0] != '\0') {
      line2 = String("ERR:") + String(lastCommandError);
    }
    lcdStatus(line1, line2);
  } else {
    if (WiFi.status() == WL_CONNECTED) {
      String line1 = "WiFi OK " + String(WiFi.RSSI()) + "dBm";
      String line2 = ipToString(WiFi.localIP());
      lcdStatus(line1, line2);
    } else {
      lcdStatus("WiFi status", "Disconnected");
    }
  }
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

void prepareHttpClient() {
  // Reset stale TLS sockets before starting a new request.
  sslClient.stop();
  delay(30);
}

bool isWifiHealthy() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  long rssi = WiFi.RSSI();
  if (rssi < -90) {
    return false;
  }

  IPAddress testIp;
  if (!WiFi.hostByName(API_HOST, testIp)) {
    return false;
  }

  return true;
}

bool connectWifi() {
  lcdTransient("WiFi verbinden", WIFI_SSID, 2000);
  Serial.print("Connecting to WiFi");
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_CONNECT_TIMEOUT_MS) {
    Serial.print(".");
    delay(400);
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println();
    Serial.println("WiFi connect timeout");
    setApiStatus("error", "wifi timeout");
    lcdTransient("WiFi timeout", "Reconnect fail", 2000);
    printWifiStatus();
    return false;
  }

  Serial.println();
  Serial.print("WiFi connected. IP: ");
  Serial.println(WiFi.localIP());
  setApiStatus("ok", "wifi connected");
  lcdTransient("WiFi OK", ipToString(WiFi.localIP()), 2000);
  printWifiStatus();
  return true;
}

bool pollCommand(int &commandId, String &token, int &pulseMs, bool &pollHealthy) {
  pollHealthy = false;
  prepareHttpClient();
  HttpClient http(sslClient, API_HOST, API_PORT);
  String path = String(API_BASE_PATH) + "/device_poll.php";

  Serial.print("Polling: https://");
  Serial.print(API_HOST);
  Serial.print(path);
  Serial.println();

  http.beginRequest();
  http.get(path);
  http.sendHeader("X-DEVICE-KEY", DEVICE_KEY);
  http.endRequest();

  int code = http.responseStatusCode();
  if (code < 0) {
    http.stop();
    Serial.print("poll transport error: ");
    Serial.println(code);
    char netMsg[24];
    snprintf(netMsg, sizeof(netMsg), "net %d", code);
    setApiStatus("error", String(netMsg));
    printRuntimeStatus();
    return false;
  }

  if (code != 200) {
    String errorBody = http.responseBody();
    http.stop();
    pollHealthy = true;
    Serial.print("poll status: ");
    Serial.println(code);
    lcdTransient("Poll fout", String(code), 2000);
    setApiStatus("error", "HTTP " + String(code));
    if (errorBody.length() > 0) {
      Serial.print("poll body: ");
      Serial.println(errorBody);
      setApiStatus("error", errorBody.substring(0, 15));
    }
    printRuntimeStatus();
    return false;
  }

  String body = http.responseBody();
  http.stop();

  Serial.print("poll status: ");
  Serial.println(code);
  Serial.print("poll body: ");
  Serial.println(body);
  setApiStatus("ok", "poll 200");

  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, body);
  if (err) {
    Serial.print("JSON parse error: ");
    Serial.println(err.c_str());
    setApiStatus("error", "json parse");
    printRuntimeStatus();
    return false;
  }

  pollHealthy = true;

  bool hasCommand = doc["has_command"] | false;
  if (!hasCommand) {
    setApiStatus("ok", "no command");
    printRuntimeStatus();
    return false;
  }

  commandId = doc["command"]["id"] | 0;
  token = String((const char*)doc["command"]["token"]);
  pulseMs = doc["command"]["pulse_ms"] | 1200;

  return commandId > 0 && token.length() > 10;
}

bool ackCommand(int commandId, const String &token, String &ackError) {
  prepareHttpClient();
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
  String body = http.responseBody();
  http.stop();

  Serial.print("ack status: ");
  Serial.println(code);
  if (body.length() > 0) {
    Serial.print("ack body: ");
    Serial.println(body);
  }
  lcdTransient("ACK status", String(code), 1500);

  if (code == 200) {
    setApiStatus("ok", "ack 200");
    ackError = "";
  } else {
    char ackMsg[24];
    snprintf(ackMsg, sizeof(ackMsg), "ack %d", code);
    setApiStatus("error", String(ackMsg));
    char ackErrBuf[40];
    snprintf(ackErrBuf, sizeof(ackErrBuf), "http%d", code);
    ackError = String(ackErrBuf);
    if (body.length() > 0) {
      ackError += ":" + body.substring(0, 10);
    }
  }
  printRuntimeStatus();

  return code == 200;
}

void startRelayPulse() {
  // Re-assert output mode before switching the relay for extra safety.
  pinMode(RELAY_PIN, OUTPUT);
  Serial.println("Relay pulse start");
  setLastCommand("open", "running", "");
  printRuntimeStatus();
  lcdTransient("Deur openen", String(RELAY_PULSE_MS) + "ms", RELAY_PULSE_MS + 400);
  digitalWrite(RELAY_PIN, RELAY_ACTIVE_STATE);
  relayActive = true;
  relayOffAtMs = millis() + RELAY_PULSE_MS;
  relayFinishedEvent = false;
}

void updateRelayState() {
  if (relayActive && millis() >= relayOffAtMs) {
    pinMode(RELAY_PIN, OUTPUT);
    digitalWrite(RELAY_PIN, RELAY_INACTIVE_STATE);
    relayActive = false;
    relayFinishedEvent = true;
    Serial.println("Relay pulse done");
    lcdTransient("Deur geopend", "Klaar", 2000);
  }

  // Keep relay pin in a known safe state while idle.
  if (!relayActive && millis() - lastRelaySafetyMs >= RELAY_SAFETY_REFRESH_MS) {
    pinMode(RELAY_PIN, OUTPUT);
    digitalWrite(RELAY_PIN, RELAY_INACTIVE_STATE);
    lastRelaySafetyMs = millis();
  }
}

void setup() {
  wdt_disable();
  Serial.begin(115200);
  unsigned long serialWaitStart = millis();
  while (!Serial && millis() - serialWaitStart < 3000) {
    ;
  }

  Serial.println("Doorbell client starting...");
  lcd.begin(16, 2);
  lcdTransient("Deurbel client", "Opstarten...", 2000);

  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, RELAY_INACTIVE_STATE);
  lastRelaySafetyMs = millis();
  pinMode(STATUS_LED_PIN, OUTPUT);
  digitalWrite(STATUS_LED_PIN, LOW);
  connectWifi();
  lastPollSuccessMs = millis();
  consecutivePollFailures = 0;
  lastLcdRotateMs = millis();
  renderSummaryLcd();
  wdt_enable(WDTO_8S);
}

void loop() {
  wdt_reset();
  updateRelayState();

  if (!isWifiHealthy()) {
    Serial.println("WiFi disconnected, reconnecting...");
    setApiStatus("error", "wifi reconnect");
    lcdTransient("WiFi weg", "Reconnect...", 2000);
    sslClient.stop();
    WiFi.disconnect();
    delay(500);
    if (!connectWifi()) {
      delay(800);
      return;
    }
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
  bool pollHealthy = false;

  Serial.println("poll tick");

  if (pollCommand(commandId, token, pulseMs, pollHealthy)) {
    Serial.print("Command received: ");
    Serial.println(commandId);
    setLastCommand(String(commandId), "received", "");
    printRuntimeStatus();
    lcdTransient("Opdracht", String(commandId), 1500);

    if (!relayActive && !pendingAck) {
      (void)pulseMs;
      startRelayPulse();
      pendingAck = true;
      pendingAckCommandId = commandId;
      token.toCharArray(pendingAckToken, sizeof(pendingAckToken));
      snprintf(pendingAckLabel, sizeof(pendingAckLabel), "%d", commandId);
    } else {
      Serial.println("Relay busy; skipping duplicate command until current cycle finishes");
      setLastCommand(String(commandId), "busy", "relay active");
    }
    printRuntimeStatus();
  }

  if (pendingAck && !relayActive && (relayFinishedEvent || millis() - lastAckAttemptMs >= ACK_RETRY_INTERVAL_MS)) {
    relayFinishedEvent = false;
    lastAckAttemptMs = millis();
    String ackError = "";
    bool ackOk = ackCommand(pendingAckCommandId, String(pendingAckToken), ackError);
    if (ackOk) {
      setLastCommand(String(pendingAckLabel), "acked", "");
      pendingAck = false;
      pendingAckCommandId = 0;
      pendingAckToken[0] = '\0';
      pendingAckLabel[0] = '\0';
      // Reset watchdog timers so ACK latency doesn't trigger reconnect.
      lastPollSuccessMs = millis();
      consecutivePollFailures = 0;
      lastPollMs = millis() - POLL_INTERVAL_MS;
    } else {
      setLastCommand(String(pendingAckLabel), "ack_error", ackError.length() > 0 ? ackError : "api");
    }
    printRuntimeStatus();
    return;
  }

  if (pollHealthy) {
    consecutivePollFailures = 0;
    lastPollSuccessMs = millis();
  } else {
    consecutivePollFailures++;
  }

  unsigned long nowMs = millis();
  if (consecutivePollFailures >= MAX_POLL_FAILURES_BEFORE_RECONNECT ||
      nowMs - lastPollSuccessMs > MAX_POLL_STALE_MS) {
    Serial.println("Poll watchdog: forcing WiFi reconnect...");
    setApiStatus("error", "poll watchdog");
    lcdTransient("Netwerk reset", "Watchdog", 1500);
    sslClient.stop();
    WiFi.disconnect();
    delay(250);
    watchdogReconnectCount++;
    if (!connectWifi()) {
      delay(800);
      return;
    }
    consecutivePollFailures = 0;
    lastPollSuccessMs = millis();
  }

  if (pollHealthy) {
    watchdogReconnectCount = 0;
  }

  if (millis() > lcdTransientUntilMs && millis() - lastLcdRotateMs > LCD_ROTATE_INTERVAL_MS) {
    lastLcdRotateMs = millis();
    lcdPage = (lcdPage + 1) % 3;
    renderSummaryLcd();
  }
}
