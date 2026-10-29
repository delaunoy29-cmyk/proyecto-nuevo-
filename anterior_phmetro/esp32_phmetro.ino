/*
  Ejemplo: ESP32 + módulo pH analógico (PH-4502C, DFRobot SEN0161/SEN0169 o similar)
  Lee el pH cada 60 s y lo envía a https://servimaxx.cl/phmetro/api.php

  Conexión típica: salida analógica del módulo (Po) -> GPIO34 del ESP32
  OJO: el ESP32 acepta máx. 3,3 V en sus pines. Si tu módulo entrega hasta 5 V,
  usa un divisor de tensión (ej. 10k/20k).

  CALIBRACIÓN: mide el voltaje con buffer pH 7 y pH 4 (o 10) y escribe los valores abajo.
*/
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>

const char* WIFI_SSID = "TU_WIFI";
const char* WIFI_PASS = "TU_CLAVE_WIFI";
const char* URL       = "https://servimaxx.cl/phmetro/api.php";
const char* API_KEY   = "CAMBIA_ESTA_CLAVE_LARGA_DEL_EQUIPO";  // igual que en config.php
const char* EQUIPO    = "PH-01";

const int   PIN_PH    = 34;
const float V_PH7     = 1.65;   // voltaje medido en buffer pH 7  (calibrar)
const float V_PH4     = 2.03;   // voltaje medido en buffer pH 4  (calibrar)
const unsigned long INTERVALO_MS = 60000;

float leerVoltaje() {
  long suma = 0;
  for (int i = 0; i < 40; i++) { suma += analogReadMilliVolts(PIN_PH); delay(10); }
  return (suma / 40.0) / 1000.0;
}

float voltajeApH(float v) {
  float pendiente = (7.0 - 4.0) / (V_PH7 - V_PH4);
  return 7.0 + (v - V_PH7) * pendiente;
}

void conectarWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  for (int i = 0; i < 40 && WiFi.status() != WL_CONNECTED; i++) delay(500);
}

void setup() {
  Serial.begin(115200);
  analogSetAttenuation(ADC_11db);
  conectarWiFi();
}

void loop() {
  conectarWiFi();
  float v  = leerVoltaje();
  float ph = voltajeApH(v);
  Serial.printf("V=%.3f  pH=%.2f\n", v, ph);

  if (WiFi.status() == WL_CONNECTED) {
    WiFiClientSecure cliente;
    cliente.setInsecure();               // simple; para producción carga el certificado
    HTTPClient http;
    http.begin(cliente, URL);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", API_KEY);
    String cuerpo = String("{\"equipo\":\"") + EQUIPO + "\",\"ph\":" + String(ph, 2) + "}";
    int code = http.POST(cuerpo);
    Serial.printf("HTTP %d: %s\n", code, http.getString().c_str());
    http.end();
  }
  delay(INTERVALO_MS);
}
