# SERVIMAXX Monitoreo

Plataforma web multi-cliente para monitorear equipos en terreno: pH metros, galpones avícolas (temperatura, humedad, NH3, CO2) y pivotes de riego (GPS). Cada cliente entra con su usuario y ve solo sus equipos; el administrador ve todo. Funciona en https://servimaxx.cl/monitoreo (Host.cl, PHP + MySQL).

El resumen completo del proyecto, con la cronología, el presupuesto por galpón y los pendientes, está en [RESUMEN.txt](RESUMEN.txt).

## Carpetas

| Carpeta | Qué contiene |
|---|---|
| [servidor/monitoreo](servidor/monitoreo) | La plataforma: login, panel de equipos, gráficos, alarmas por correo, CSV, mapa de pivotes y la API que reciben las tarjetas. La guía de instalación está en [LEEME.txt](servidor/monitoreo/LEEME.txt). |
| [servidor/monitoreo/firmware/esp32_monitoreo](servidor/monitoreo/firmware/esp32_monitoreo) | Programa único para ESP32 por WiFi (módulo pH, avícola o pivote). |
| [tarjeta_4g_galpon](tarjeta_4g_galpon) | Programa de la tarjeta del galpón con 4G (LilyGO T-SIM7600SA-H o T-A7670SA) y cuatro transmisores 4–20 mA. Guarda hasta 24 h sin señal. |
| [planos](planos) | Planos AVI-01 (conexionado del tablero) y AVI-02 (ubicación de instrumentos y materiales). |
| [anterior_phmetro](anterior_phmetro) | Primera versión, solo pH (servimaxx.cl/phmetro). Ya fue reemplazada por la plataforma. |

## Claves

Esta copia no tiene claves reales. En `servidor/monitoreo/config.php` las claves dicen `CAMBIAR_CLAVE` y en los programas de las tarjetas dicen `PEGAR_CLAVE_API_DEL_EQUIPO`. Complétalas solo en el servidor y en las tarjetas, nunca aquí: si subes una clave a GitHub, cámbiala.
