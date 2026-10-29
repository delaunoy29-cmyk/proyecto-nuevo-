/*
  SERVIMAXX · Tarjeta de galpón avícola (GAL-xx)
  ESP32 + módem 4G (LilyGO T-SIM7600SA-H o T-A7670SA) + ADS1115
  Mide 4 transmisores 4–20 mA y envía a la plataforma SERVIMAXX Monitoreo
  (https://servimaxx.cl/monitoreo/api.php, equipo tipo "Galpón avícola").

  Canales ADS1115 (shunt 150 Ω 0,1 % en cada uno -> 0,6–3,0 V):
    A0  Temperatura   (TT)  4–20 mA = -20 … 80 °C
    A1  Humedad rel.  (HT)  4–20 mA =   0 … 100 %
    A2  Amoníaco NH3  (AT)  4–20 mA =   0 … 100 ppm
    A3  CO2           (AT)  4–20 mA =   0 … 5000 ppm
  Ajusta los rangos abajo según la placa de cada transmisor.

  Librerías (Arduino IDE): "TinyGSM" (Volodymyr Shymanskyy), "Adafruit ADS1X15".
  Placa en Arduino IDE: "ESP32 Dev Module".
*/
#define TINY_GSM_MODEM_SIM7600
#define TINY_GSM_RX_BUFFER 1024
#include <TinyGsmClient.h>
#include <Wire.h>
#include <Adafruit_ADS1X15.h>

// ---------- Identificación ----------
const char* URL     = "https://servimaxx.cl/monitoreo/api.php";
const char* API_KEY = "PEGAR_CLAVE_API_DEL_EQUIPO";      // Administrar > equipo (una por galpón)
const char* APN     = "imovil.entelpcs.cl";               // Entel. Movistar: "wap.tmovil.cl" · Claro: "bam.clarochile.cl" (verificar)
const char* APN_USR = "entelpcs";
const char* APN_PWD = "entelpcs";

// ---------- Modelo de placa LilyGO: deja SOLO UNA línea sin "//" ----------
// Mira la etiqueta del módulo 4G (la lata metálica): "SIM7600SA-H"/"SIM7600G-H" o "A7670SA"
#define PLACA_T_SIM7600        // LilyGO T-SIM7600 (SIM7600SA-H / G-H)
// #define PLACA_T_A7670       // LilyGO T-A7670SA (bandas Latinoamérica)

#if defined(PLACA_T_SIM7600)
  #define MODEM_TX       27
  #define MODEM_RX       26
  #define MODEM_PWRKEY    4
  #define MODEM_POWERON  12
  #define MODEM_FLIGHT   25
#elif defined(PLACA_T_A7670)
  #define MODEM_TX       26
  #define MODEM_RX       27
  #define MODEM_PWRKEY    4
  #define MODEM_POWERON  12
  #define MODEM_FLIGHT   -1
#endif
#define I2C_SDA        21
#define I2C_SCL        22
#define PIN_RELE_ALARMA 19      // relé de baliza/sirena local (activo en HIGH)

// ---------- Rangos de transmisores ----------
struct Canal { const char* nombre; float min4, max20; };
Canal CH[4] = { {"temp", -20.0, 80.0}, {"hum", 0.0, 100.0}, {"nh3", 0.0, 100.0}, {"co2", 0.0, 5000.0} };
const float SHUNT_OHM = 150.0;

// ---------- Umbrales de alarma local (ajustar con el veterinario) ----------
const float T_MAX = 32.0, T_MIN = 16.0, NH3_MAX = 25.0, CO2_MAX = 3000.0;

// ---------- Tiempos ----------
const unsigned long PERIODO_MS = 5UL * 60UL * 1000UL;   // una lectura cada 5 min
const int BUF_MAX = 288;                                 // 24 h guardadas sin señal

HardwareSerial SerialAT(1);
TinyGsm modem(SerialAT);
Adafruit_ADS1115 ads;

struct Lectura { unsigned long ms; float v[4]; char falla[40]; };
Lectura buf[BUF_MAX];
int bufN = 0;

// ---- Lectura 4–20 mA ----
float leer_mA(int ch) {
  float s = 0;
  for (int i = 0; i < 16; i++) { s += ads.computeVolts(ads.readADC_SingleEnded(ch)); delay(3); }
  return (s / 16.0) / SHUNT_OHM * 1000.0;
}
float escalar(float mA, const Canal& c) {
  float v = c.min4 + (mA - 4.0) * (c.max20 - c.min4) / 16.0;
  return constrain(v, c.min4, c.max20);
}

Lectura medir() {
  Lectura L; L.ms = millis(); L.falla[0] = 0;
  for (int i = 0; i < 4; i++) {
    float mA = leer_mA(i);
    if (mA < 3.6 || mA > 21.0) {               // lazo abierto o transmisor en falla (NAMUR NE43)
      L.v[i] = NAN;
      snprintf(L.falla + strlen(L.falla), sizeof(L.falla) - strlen(L.falla), "%s:%.1fmA ", CH[i].nombre, mA);
    } else L.v[i] = escalar(mA, CH[i]);
  }
  return L;
}

void alarmaLocal(const Lectura& L) {
  bool a = (!isnan(L.v[0]) && (L.v[0] > T_MAX || L.v[0] < T_MIN)) ||
           (!isnan(L.v[2]) && L.v[2] > NH3_MAX) ||
           (!isnan(L.v[3]) && L.v[3] > CO2_MAX) || L.falla[0];
  digitalWrite(PIN_RELE_ALARMA, a ? HIGH : LOW);
}

// ---- Módem ----
void encenderModem() {
  pinMode(MODEM_POWERON, OUTPUT); digitalWrite(MODEM_POWERON, HIGH);
  if (MODEM_FLIGHT >= 0) { pinMode(MODEM_FLIGHT, OUTPUT); digitalWrite(MODEM_FLIGHT, HIGH); }
  pinMode(MODEM_PWRKEY, OUTPUT);
  digitalWrite(MODEM_PWRKEY, LOW); delay(100);
  digitalWrite(MODEM_PWRKEY, HIGH); delay(500);
  digitalWrite(MODEM_PWRKEY, LOW);
  delay(3000);
}
bool conectarRed() {
  if (modem.isGprsConnected()) return true;
  if (!modem.waitForNetwork(60000L)) return false;
  return modem.gprsConnect(APN, APN_USR, APN_PWD);
}

// Una lectura -> JSON con las variables de la plataforma (temp, hum, nh3, co2)
// "hace" = segundos de antigüedad (la plataforma la guarda con su hora real)
String json(const Lectura& L, int rssiDbm) {
  String j = "{\"key\":\"" + String(API_KEY) + "\"";
  for (int c = 0; c < 4; c++)
    if (!isnan(L.v[c])) j += ",\"" + String(CH[c].nombre) + "\":" + String(L.v[c], c == 3 ? 0 : 1);
  j += ",\"rssi\":" + String(rssiDbm);
  j += ",\"falla_lazo\":" + String(L.falla[0] ? 1 : 0);
  j += ",\"hace\":" + String((millis() - L.ms) / 1000UL) + "}";
  return j;
}

// HTTPS con el stack interno del SIM7600 (AT+HTTP...)
bool enviar(const String& cuerpo) {
  modem.sendAT("+HTTPTERM"); modem.waitResponse(2000);
  modem.sendAT("+HTTPINIT"); if (modem.waitResponse(5000) != 1) return false;
  modem.sendAT("+HTTPPARA=\"URL\",\"", URL, "\""); modem.waitResponse(3000);
  modem.sendAT("+HTTPPARA=\"CONTENT\",\"application/json\""); modem.waitResponse(3000);
  modem.sendAT("+HTTPDATA=", cuerpo.length(), ",10000");
  if (modem.waitResponse(5000, "DOWNLOAD") != 1) return false;
  SerialAT.print(cuerpo); modem.waitResponse(10000);
  modem.sendAT("+HTTPACTION=1");
  if (modem.waitResponse(5000) != 1) return false;
  String r;
  bool ok = false;
  if (modem.waitResponse(60000L, r, "+HTTPACTION:") == 1) {
    modem.waitResponse(1000L, r);
    ok = r.indexOf(",200,") >= 0;
  }
  modem.sendAT("+HTTPTERM"); modem.waitResponse(2000);
  return ok;
}

void setup() {
  Serial.begin(115200);
  pinMode(PIN_RELE_ALARMA, OUTPUT); digitalWrite(PIN_RELE_ALARMA, LOW);
  Wire.begin(I2C_SDA, I2C_SCL);
  if (!ads.begin(0x48)) Serial.println("ADS1115 no encontrado");
  ads.setGain(GAIN_ONE);
  SerialAT.begin(115200, SERIAL_8N1, MODEM_RX, MODEM_TX);
  encenderModem();
  modem.restart();
  Serial.println(modem.getModemInfo());
}

void loop() {
  static unsigned long ultimo = 0;
  if (ultimo != 0 && millis() - ultimo < PERIODO_MS) { delay(200); return; }
  ultimo = millis();

  Lectura L = medir();
  alarmaLocal(L);
  if (bufN == BUF_MAX) { memmove(buf, buf + 1, sizeof(Lectura) * (BUF_MAX - 1)); bufN--; }
  buf[bufN++] = L;
  Serial.printf("T=%.1f HR=%.1f NH3=%.1f CO2=%.0f %s\n", L.v[0], L.v[1], L.v[2], L.v[3], L.falla);

  if (!conectarRed()) { Serial.println("Sin red 4G, se guarda"); return; }
  int csq = modem.getSignalQuality();
  int dbm = (csq > 0 && csq < 99) ? -113 + 2 * csq : -120;
  int enviadas = 0;
  while (bufN > 0 && enviadas < 30) {       // de la más antigua a la más nueva
    if (!enviar(json(buf[0], dbm))) { Serial.println("Error envío, reintenta luego"); break; }
    memmove(buf, buf + 1, sizeof(Lectura) * (bufN - 1));
    bufN--; enviadas++;
  }
}
