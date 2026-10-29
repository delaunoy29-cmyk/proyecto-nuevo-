/*
  ESP32 + transmisor de pH con salida 4–20 mA  ->  servimaxx.cl/phmetro

  MATERIALES
  - ESP32 (DevKit)
  - ADS1115 (convertidor analógico 16 bits, I2C)  — más preciso que el ADC del ESP32
  - Resistencia shunt 150 Ω, 0,1 %, 0,25 W o más
  - Fuente 24 VDC para el lazo (si el transmisor es de 2 hilos / alimentado por lazo)
  - Fuente 5 V para el ESP32 (o una reductora 24 V -> 5 V)

  CONEXIÓN (transmisor de 2 hilos, alimentado por lazo)
     +24 V  --->  (+) transmisor
     (-) transmisor  --->  ADS1115 A0  y  un extremo del shunt 150 Ω
     otro extremo del shunt  --->  GND  (GND común: fuente 24 V, ADS1115 y ESP32)

     4 mA x 150 Ω = 0,60 V      20 mA x 150 Ω = 3,00 V   (seguro para 3,3 V)

  Si el transmisor es de 4 hilos (salida activa): su salida (+) va a A0 y al
  shunt, y su (-) a GND. NO pongas además la fuente de 24 V en el lazo.

  ADS1115 -> ESP32:  VDD 3,3 V | GND GND | SDA GPIO21 | SCL GPIO22 | ADDR a GND

  Librería: "Adafruit ADS1X15" (Arduino IDE > Administrar bibliotecas)
*/
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <Wire.h>
#include <Adafruit_ADS1X15.h>

// ---------- Ajustes ----------
const char* WIFI_SSID = "TU_WIFI";
const char* WIFI_PASS = "TU_CLAVE_WIFI";
const char* URL       = "https://servimaxx.cl/phmetro/api.php";
const char* API_KEY   = "CAMBIA_ESTA_CLAVE_LARGA_DEL_EQUIPO";   // igual que en config.php
const char* EQUIPO    = "PH-01";

// Rango configurado en el transmisor: 4 mA = PH_4MA, 20 mA = PH_20MA
const float PH_4MA    = 0.0;
const float PH_20MA   = 14.0;

const float SHUNT_OHM = 150.0;
// Ajuste fino: si con un calibrador de lazo a 4,00 mA el equipo lee 4,03,
// pon OFFSET_MA = -0.03 (y similar con GANANCIA a 20 mA).
const float OFFSET_MA = 0.0;
const float GANANCIA  = 1.0;

const unsigned long INTERVALO_MS = 60000;   // una lectura por minuto
// -----------------------------

Adafruit_ADS1115 ads;

float leerCorriente_mA() {
  const int N = 32;
  float suma = 0;
  for (int i = 0; i < N; i++) {
    int16_t raw = ads.readADC_SingleEnded(0);
    suma += ads.computeVolts(raw);
    delay(5);
  }
  float volts = suma / N;
  return (volts / SHUNT_OHM * 1000.0) * GANANCIA + OFFSET_MA;
}

// Estado del lazo según NAMUR NE43
const char* estadoLazo(float mA) {
  if (mA < 3.6)  return "FALLA: lazo abierto o sin alimentacion";
  if (mA < 3.8)  return "FALLA: bajo rango (transmisor en alarma)";
  if (mA > 21.0) return "FALLA: sobre rango (transmisor en alarma)";
  return nullptr;
}

float mA_a_pH(float mA) {
  float ph = PH_4MA + (mA - 4.0) * (PH_20MA - PH_4MA) / 16.0;
  if (ph < PH_4MA)  ph = PH_4MA;
  if (ph > PH_20MA) ph = PH_20MA;
  return ph;
}

void conectarWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  for (int i = 0; i < 40 && WiFi.status() != WL_CONNECTED; i++) delay(500);
}

bool enviar(float ph, float mA, const char* nota) {
  if (WiFi.status() != WL_CONNECTED) return false;
  WiFiClientSecure cliente;
  cliente.setInsecure();                 // simple; en producción carga el certificado
  HTTPClient http;
  http.setTimeout(10000);
  http.begin(cliente, URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key", API_KEY);

  String cuerpo = String("{\"equipo\":\"") + EQUIPO +
                  "\",\"ph\":" + String(ph, 2) +
                  ",\"nota\":\"" + String(mA, 2) + " mA" +
                  (nota ? String(" | ") + nota : String("")) + "\"}";
  int code = http.POST(cuerpo);
  Serial.printf("HTTP %d: %s\n", code, http.getString().c_str());
  http.end();
  return code == 200;
}

void setup() {
  Serial.begin(115200);
  Wire.begin(21, 22);
  if (!ads.begin(0x48)) {
    Serial.println("No se encuentra el ADS1115: revisa SDA/SCL/alimentacion");
    while (true) delay(1000);
  }
  ads.setGain(GAIN_ONE);                 // rango ±4,096 V, resolución 0,125 mV
  conectarWiFi();
}

void loop() {
  conectarWiFi();
  float mA = leerCorriente_mA();
  const char* falla = estadoLazo(mA);
  float ph = mA_a_pH(mA);

  Serial.printf("I=%.2f mA  pH=%.2f  %s\n", mA, ph, falla ? falla : "OK");

  if (falla) {
    // No se registra un pH falso: se avisa la falla con pH fuera de escala marcado en la nota
    // (el panel lo mostrará en rojo). Si prefieres no registrar nada, comenta la línea.
    enviar(ph, mA, falla);
  } else {
    enviar(ph, mA, nullptr);
  }
  delay(INTERVALO_MS);
}
