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
const int MAX_POLL_FAILURES_BEFORE_RECONNECT = 60;
const unsigned long MAX_POLL_STALE_MS = 180000;
const unsigned long WIFI_CONNECT_TIMEOUT_MS = 45000;
int watchdogReconnectCount = 0;
const bool ENABLE_WATCHDOG = false;
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

void safeCopy(char *dest, size_t destSize, const char *src) {
  if (destSize == 0) {
    return;
  }
  if (src == nullptr) {
    dest[0] = '\0';
    return;
  }
  strncpy(dest, src, destSize - 1);
  dest[destSize - 1] = '\0';
}

void ipToBuffer(const IPAddress &ip, char *buf, size_t bufSize) {
  if (bufSize == 0) {
    return;
  }
  snprintf(buf, bufSize, "%u.%u.%u.%u", ip[0], ip[1], ip[2], ip[3]);
}

void lcdStatusC(const char *line1, const char *line2 = "") {
  char l1[17];
  char l2[17];
  safeCopy(l1, sizeof(l1), line1);
  safeCopy(l2, sizeof(l2), line2);
  l1[16] = '\0';
  l2[16] = '\0';

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(l1);
  lcd.setCursor(0, 1);
  lcd.print(l2);
}

void lcdTransientC(const char *line1, const char *line2 = "", unsigned long durationMs = 1500) {
  lcdStatusC(line1, line2);
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

void setApiStatusC(const char *status, const char *message) {
  safeCopy(apiStatus, sizeof(apiStatus), status);
  safeCopy(apiMessage, sizeof(apiMessage), message);
  apiTs = millis();
}

void setLastCommandC(const char *command, const char *status, const char *errorMsg) {
  safeCopy(lastCommand, sizeof(lastCommand), command);
  safeCopy(lastCommandStatus, sizeof(lastCommandStatus), status);
  safeCopy(lastCommandError, sizeof(lastCommandError), errorMsg);
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
    char line1[24];
    snprintf(line1, sizeof(line1), "API: %s", apiStatus);
    lcdStatusC(line1, apiMessage);
  } else if (lcdPage == 1) {
    char line1[24];
    char line2[24];
    snprintf(line1, sizeof(line1), "CMD:%s", lastCommand);
    safeCopy(line2, sizeof(line2), lastCommandStatus);
    if (lastCommandError[0] != '\0') {
      snprintf(line2, sizeof(line2), "ERR:%s", lastCommandError);
    }
    lcdStatusC(line1, line2);
  } else {
    if (WiFi.status() == WL_CONNECTED) {
      char line1[24];
      char ipBuf[20];
      snprintf(line1, sizeof(line1), "WiFi OK %lddBm", WiFi.RSSI());
      ipToBuffer(WiFi.localIP(), ipBuf, sizeof(ipBuf));
      lcdStatusC(line1, ipBuf);
    } else {
      lcdStatusC("WiFi status", "Disconnected");
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
  // Keep this check lightweight to avoid reconnect flapping.
  // API reachability is handled by the poll watchdog below.
  return WiFi.status() == WL_CONNECTED;
}

bool connectWifi() {
  lcdTransientC("WiFi verbinden", WIFI_SSID, 2000);
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
    setApiStatusC("error", "wifi timeout");
    lcdTransientC("WiFi timeout", "Reconnect fail", 2000);
    printWifiStatus();
    return false;
  }

  Serial.println();
  Serial.print("WiFi connected. IP: ");
  Serial.println(WiFi.localIP());
  setApiStatusC("ok", "wifi connected");
  char ipBuf[20];
  ipToBuffer(WiFi.localIP(), ipBuf, sizeof(ipBuf));
  lcdTransientC("WiFi OK", ipBuf, 2000);
  printWifiStatus();
  return true;
}

bool pollCommand(int &commandId, char *token, size_t tokenSize, int &pulseMs, bool &pollHealthy) {
  pollHealthy = false;
  prepareHttpClient();
  HttpClient http(sslClient, API_HOST, API_PORT);
  char path[48];
  snprintf(path, sizeof(path), "%s/device_poll.php", API_BASE_PATH);

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
    setApiStatusC("error", netMsg);
    printRuntimeStatus();
    return false;
  }

  if (code != 200) {
    String errorBody = http.responseBody();
    http.stop();
    pollHealthy = false;
    Serial.print("poll status: ");
    Serial.println(code);
    char codeMsg[16];
    snprintf(codeMsg, sizeof(codeMsg), "%d", code);
    lcdTransientC("Poll fout", codeMsg, 2000);
    char httpMsg[24];
    snprintf(httpMsg, sizeof(httpMsg), "HTTP %d", code);
    setApiStatusC("error", httpMsg);
    if (errorBody.length() > 0) {
      Serial.print("poll body: ");
      Serial.println(errorBody);
      char shortErr[24];
      safeCopy(shortErr, sizeof(shortErr), errorBody.c_str());
      setApiStatusC("error", shortErr);
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
  setApiStatusC("ok", "poll 200");

  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, body);
  if (err) {
    Serial.print("JSON parse error: ");
    Serial.println(err.c_str());
    setApiStatusC("error", "json parse");
    printRuntimeStatus();
    return false;
  }

  pollHealthy = true;

  bool hasCommand = doc["has_command"] | false;
  if (!hasCommand) {
    setApiStatusC("ok", "no command");
    printRuntimeStatus();
    return false;
  }

  commandId = doc["command"]["id"] | 0;
  const char *tokenVal = doc["command"]["token"] | "";
  safeCopy(token, tokenSize, tokenVal);
  pulseMs = doc["command"]["pulse_ms"] | 1200;

  return commandId > 0 && token[0] != '\0' && strlen(token) > 10;
}

bool ackCommand(int commandId, const char *token, char *ackError, size_t ackErrorSize) {
  prepareHttpClient();
  HttpClient http(sslClient, API_HOST, API_PORT);
  char path[48];
  snprintf(path, sizeof(path), "%s/device_ack.php", API_BASE_PATH);

  StaticJsonDocument<256> doc;
  doc["command_id"] = commandId;
  doc["token"] = token;

  char payload[256];
  size_t payloadLen = serializeJson(doc, payload, sizeof(payload));
  if (payloadLen == 0 || payloadLen >= sizeof(payload)) {
    safeCopy(ackError, ackErrorSize, "payload");
    setApiStatusC("error", "ack payload");
    return false;
  }

  http.beginRequest();
  http.post(path);
  http.sendHeader("Content-Type", "application/json");
  http.sendHeader("X-DEVICE-KEY", DEVICE_KEY);
  http.sendHeader("Content-Length", (int) payloadLen);
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
  char codeMsg[16];
  snprintf(codeMsg, sizeof(codeMsg), "%d", code);
  lcdTransientC("ACK status", codeMsg, 1500);

  if (code == 200) {
    setApiStatusC("ok", "ack 200");
    if (ackErrorSize > 0) {
      ackError[0] = '\0';
    }
  } else {
    char ackMsg[24];
    snprintf(ackMsg, sizeof(ackMsg), "ack %d", code);
    setApiStatusC("error", ackMsg);
    char ackErrBuf[64];
    snprintf(ackErrBuf, sizeof(ackErrBuf), "http%d", code);
    safeCopy(ackError, ackErrorSize, ackErrBuf);
    if (body.length() > 0) {
      if (ackErrorSize > 1 && strlen(ackError) < ackErrorSize - 1) {
        strncat(ackError, ":", ackErrorSize - strlen(ackError) - 1);
        strncat(ackError, body.c_str(), ackErrorSize - strlen(ackError) - 1);
      }
    }
  }
  printRuntimeStatus();

  return code == 200;
}

void startRelayPulse() {
  // Re-assert output mode before switching the relay for extra safety.
  pinMode(RELAY_PIN, OUTPUT);
  Serial.println("Relay pulse start");
  setLastCommandC("open", "running", "");
  printRuntimeStatus();
  char pulseMsg[16];
  snprintf(pulseMsg, sizeof(pulseMsg), "%dms", RELAY_PULSE_MS);
  lcdTransientC("Deur openen", pulseMsg, RELAY_PULSE_MS + 400);
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
    lcdTransientC("Deur geopend", "Klaar", 2000);
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
  lcdTransientC("Deurbel client", "Opstarten...", 2000);

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
  if (ENABLE_WATCHDOG) {
#if defined(WDTO_8S)
    wdt_enable(WDTO_8S);
#elif defined(WDTO_4S)
    wdt_enable(WDTO_4S);
#elif defined(WDTO_2S)
    wdt_enable(WDTO_2S);
#else
    wdt_enable(WDTO_1S);
#endif
  }
}

void loop() {
  if (ENABLE_WATCHDOG) {
    wdt_reset();
  }
  updateRelayState();

  if (!isWifiHealthy()) {
    Serial.println("WiFi disconnected, reconnecting...");
    setApiStatusC("error", "wifi reconnect");
    lcdTransientC("WiFi weg", "Reconnect...", 2000);
    Serial.println("WATCHDOG RECONNECT");
    Serial.print("Failures: ");
    Serial.println(consecutivePollFailures);
    Serial.print("Last success age: ");
    Serial.println(millis() - lastPollSuccessMs);
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

  lastPollMs += POLL_INTERVAL_MS; if (lastPollMs == 0 || lastPollMs > now) lastPollMs = now;

  int commandId = 0;
  int pulseMs = 1200;
  char token[96] = "";
  bool pollHealthy = false;

  Serial.println("poll tick");

  if (pollCommand(commandId, token, sizeof(token), pulseMs, pollHealthy)) {
    Serial.print("Command received: ");
    Serial.println(commandId);
    char cmdLabel[24];
    snprintf(cmdLabel, sizeof(cmdLabel), "%d", commandId);
    setLastCommandC(cmdLabel, "received", "");
    printRuntimeStatus();
    lcdTransientC("Opdracht", cmdLabel, 1500);

    if (!relayActive && !pendingAck) {
      (void)pulseMs;
      startRelayPulse();
      pendingAck = true;
      pendingAckCommandId = commandId;
      safeCopy(pendingAckToken, sizeof(pendingAckToken), token);
      snprintf(pendingAckLabel, sizeof(pendingAckLabel), "%d", commandId);
    } else {
      Serial.println("Relay busy; skipping duplicate command until current cycle finishes");
      char busyLabel[24];
      snprintf(busyLabel, sizeof(busyLabel), "%d", commandId);
      setLastCommandC(busyLabel, "busy", "relay active");
    }
    printRuntimeStatus();
  }

  if (pendingAck && !relayActive && (relayFinishedEvent || millis() - lastAckAttemptMs >= ACK_RETRY_INTERVAL_MS)) {
    relayFinishedEvent = false;
    lastAckAttemptMs = millis();
    char ackError[64] = "";
    bool ackOk = ackCommand(pendingAckCommandId, pendingAckToken, ackError, sizeof(ackError));
    if (ackOk) {
      setLastCommandC(pendingAckLabel, "acked", "");
      pendingAck = false;
      pendingAckCommandId = 0;
      pendingAckToken[0] = '\0';
      pendingAckLabel[0] = '\0';
      // Reset watchdog timers so ACK latency doesn't trigger reconnect.
      lastPollSuccessMs = millis();
      consecutivePollFailures = 0;
      lastPollMs = millis() - POLL_INTERVAL_MS;
    } else {
      setLastCommandC(pendingAckLabel, "ack_error", ackError[0] != '\0' ? ackError : "api");
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
    setApiStatusC("error", "poll watchdog");
    lcdTransientC("Netwerk reset", "Watchdog", 1500);
    Serial.println("WATCHDOG RECONNECT");
    Serial.print("Failures: ");
    Serial.println(consecutivePollFailures);
    Serial.print("Last success age: ");
    Serial.println(millis() - lastPollSuccessMs);
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
