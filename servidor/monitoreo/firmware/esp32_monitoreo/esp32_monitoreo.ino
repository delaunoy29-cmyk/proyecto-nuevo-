/* =====================================================================
   SERVIMAXX Monitoreo - Firmware único para ESP32
   Un mismo programa para pH, galpón avícola o pivote de riego.

   1) Elige el MODULO (abajo)
   2) Completa WiFi, URL y API_KEY (la ves en Administrar > equipo)
   3) Instala las librerías del módulo elegido (Herramientas > Administrar bibliotecas)
   4) Placa: "ESP32 Dev Module" y Subir
   ===================================================================== */

// ---------- 1. MÓDULO: deja SOLO UNA línea sin "//" ----------
#define MODULO_PH
// #define MODULO_AVICOLA
// #define MODULO_PIVOTE

// ---------- 2. CONEXIÓN ----------
const char* WIFI_SSID = "NOMBRE_WIFI";
const char* WIFI_PASS = "CLAVE_WIFI";
const char* API_URL   = "https://servimaxx.cl/monitoreo/api.php";
const char* API_KEY   = "PEGAR_CLAVE_API_DEL_EQUIPO";
const unsigned long INTERVALO_MS = 60000;   // cada cuánto envía (60 s)

// ---------- Ajustes de entradas 4-20 mA (ADS1115 + shunt) ----------
const float SHUNT_OHM = 150.0;   // resistencia de 150 Ω 0,1 %  → 4 mA = 0,6 V ; 20 mA = 3,0 V

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
float leerMA(int canal);            // definida más abajo

// =====================================================================
#if defined(MODULO_PH)
// Librería: "Adafruit ADS1X15"
// Cableado: ADS1115 SDA→GPIO21, SCL→GPIO22, VDD→3V3, GND→GND
//           Lazo 4-20 mA pasa por el shunt de 150 Ω entre A0 y GND
#include <Adafruit_ADS1X15.h>
Adafruit_ADS1115 ads;
const float PH_EN_4MA  = 0.0;    // pH que corresponde a 4 mA  (según configuración del transmisor)
const float PH_EN_20MA = 14.0;   // pH que corresponde a 20 mA

void iniciarSensores() {
  if (!ads.begin()) Serial.println("ERROR: no se encuentra ADS1115");
  ads.setGain(GAIN_ONE);         // ±4,096 V
}
String leerDatos() {
  float ma = leerMA(0);
  if (ma < 3.5) return "ma=" + String(ma, 2);        // lazo abierto: solo informa la corriente
  float ph = PH_EN_4MA + (ma - 4.0) * (PH_EN_20MA - PH_EN_4MA) / 16.0;
  return "ph=" + String(ph, 2) + "&ma=" + String(ma, 2);
}

// =====================================================================
#elif defined(MODULO_AVICOLA)
// Librerías: "Adafruit SHT31", "Adafruit ADS1X15"
// SHT31 (temp/humedad) y ADS1115 en el mismo bus I2C (GPIO21/22)
// MH-Z19B (CO2): TX del sensor→GPIO16, RX del sensor→GPIO17, alimentar 5 V
// Sensor NH3 industrial 4-20 mA (0-100 ppm) → shunt 150 Ω en A0 del ADS1115
#include <Adafruit_SHT31.h>
#include <Adafruit_ADS1X15.h>
Adafruit_SHT31 sht;
Adafruit_ADS1115 ads;
const float NH3_PPM_20MA = 100.0;  // rango del sensor de amoníaco

void iniciarSensores() {
  if (!sht.begin(0x44)) Serial.println("ERROR: no se encuentra SHT31");
  if (!ads.begin()) Serial.println("ERROR: no se encuentra ADS1115");
  ads.setGain(GAIN_ONE);
  Serial2.begin(9600, SERIAL_8N1, 16, 17);
}
int leerCO2() {
  const byte cmd[9] = {0xFF, 0x01, 0x86, 0, 0, 0, 0, 0, 0x79};
  byte r[9];
  while (Serial2.available()) Serial2.read();
  Serial2.write(cmd, 9);
  if (Serial2.readBytes(r, 9) != 9 || r[0] != 0xFF || r[1] != 0x86) return -1;
  byte suma = 0; for (int i = 1; i < 8; i++) suma += r[i];
  if ((byte)(0xFF - suma + 1) != r[8]) return -1;
  return r[2] * 256 + r[3];
}
String leerDatos() {
  String d = "";
  float t = sht.readTemperature(), h = sht.readHumidity();
  if (!isnan(t)) d += "temp=" + String(t, 1) + "&hum=" + String(h, 0);
  float ma = leerMA(0);
  if (ma >= 3.5) d += "&nh3=" + String(max(0.0f, (ma - 4.0f) * NH3_PPM_20MA / 16.0f), 1);
  int co2 = leerCO2();
  if (co2 > 0) d += "&co2=" + String(co2);
  return d;
}

// =====================================================================
#elif defined(MODULO_PIVOTE)
// Librería: "TinyGPSPlus"
// GPS NEO-6M/NEO-M8N: TX del GPS→GPIO16, RX del GPS→GPIO17, VCC 3V3/5V
// Montar el GPS en la ÚLTIMA torre del pivote. El ángulo lo calcula la
// plataforma con el centro configurado en Administrar.
// Opcional: transmisor de presión 4-20 mA (0-10 bar) en A0 de un ADS1115
#include <TinyGPSPlus.h>
TinyGPSPlus gps;
#define USAR_PRESION 0             // 1 si instalas ADS1115 + transmisor de presión
#if USAR_PRESION
#include <Adafruit_ADS1X15.h>
Adafruit_ADS1115 ads;
const float BAR_EN_20MA = 10.0;
#endif

void iniciarSensores() {
  Serial2.begin(9600, SERIAL_8N1, 16, 17);
#if USAR_PRESION
  ads.begin(); ads.setGain(GAIN_ONE);
#endif
}
void alimentarGPS(unsigned long ms) {
  unsigned long t = millis();
  while (millis() - t < ms) while (Serial2.available()) gps.encode(Serial2.read());
}
String leerDatos() {
  alimentarGPS(1500);
  String d = "";
  if (gps.location.isValid() && gps.location.age() < 10000) {
    d += "lat=" + String(gps.location.lat(), 6) + "&lon=" + String(gps.location.lng(), 6);
    if (gps.speed.isValid()) d += "&vel=" + String(gps.speed.kmph() * 1000.0, 1);   // m/h
  } else {
    Serial.println("GPS sin señal (sats: " + String(gps.satellites.value()) + ")");
  }
#if USAR_PRESION
  float ma = leerMA(0);
  if (ma >= 3.5) d += String(d.length() ? "&" : "") + "presion=" + String((ma - 4.0) * BAR_EN_20MA / 16.0, 2);
#endif
  return d;
}
#endif
// =====================================================================

#if defined(MODULO_PH) || defined(MODULO_AVICOLA) || (defined(MODULO_PIVOTE) && USAR_PRESION)
float leerMA(int canal) {           // promedio de 20 lecturas → mA
  long suma = 0;
  for (int i = 0; i < 20; i++) { suma += ads.readADC_SingleEnded(canal); delay(5); }
  float volt = ads.computeVolts(suma / 20);
  return volt / SHUNT_OHM * 1000.0;
}
#endif

int fallos = 0;

void conectarWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("Conectando WiFi");
  for (int i = 0; i < 40 && WiFi.status() != WL_CONNECTED; i++) { delay(500); Serial.print("."); }
  Serial.println(WiFi.status() == WL_CONNECTED ? " OK " + WiFi.localIP().toString() : " FALLÓ");
}

bool enviar(const String& datos) {
  if (datos.length() == 0) { Serial.println("Sin datos para enviar"); return true; }
  WiFiClientSecure cli;
  cli.setInsecure();                // Host.cl usa HTTPS; no valida certificado para simplificar
  HTTPClient http;
  String url = String(API_URL) + "?key=" + API_KEY + "&" + datos + "&rssi=" + String(WiFi.RSSI());
  http.setTimeout(10000);
  if (!http.begin(cli, url)) return false;
  int cod = http.GET();
  Serial.printf("Envío %s → HTTP %d %s\n", datos.c_str(), cod, cod > 0 ? http.getString().c_str() : "");
  http.end();
  return cod == 200;
}

void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\nSERVIMAXX Monitoreo");
  iniciarSensores();
  conectarWiFi();
}

unsigned long ultimo = 0;
void loop() {
#if defined(MODULO_PIVOTE)
  alimentarGPS(50);
#endif
  if (ultimo == 0 || millis() - ultimo >= INTERVALO_MS) {
    ultimo = millis();
    conectarWiFi();
    String d = leerDatos();
    if (WiFi.status() == WL_CONNECTED && enviar(d)) fallos = 0;
    else if (++fallos >= 10) { Serial.println("10 fallos seguidos, reiniciando"); ESP.restart(); }
  }
  delay(20);
}
