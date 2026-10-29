plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "cl.servimaxx.monitoreo"
    compileSdk = 35

    defaultConfig {
        applicationId = "cl.servimaxx.monitoreo"
        minSdk = 24
        targetSdk = 34
        // GitHub Actions pasa el número de compilación para que cada APK sea una actualización.
        versionCode = (System.getenv("VERSION_CODE") ?: "1").toInt()
        versionName = "1.0." + (System.getenv("VERSION_CODE") ?: "0")
        buildConfigField("String", "URL_INICIO", "\"https://servimaxx.cl/monitoreo/index.php\"")
        buildConfigField("String", "DOMINIO", "\"servimaxx.cl\"")
    }

    signingConfigs {
        // Llave de firma permanente: se usa si el repositorio tiene los secretos
        // FIRMA_ARCHIVO (la llave .jks en base64) y FIRMA_CLAVE. Sin ellos se firma con
        // una llave de prueba y, para actualizar, hay que desinstalar la versión anterior.
        val archivo = System.getenv("FIRMA_ARCHIVO_RUTA")
        if (!archivo.isNullOrEmpty()) {
            create("permanente") {
                storeFile = file(archivo)
                storePassword = System.getenv("FIRMA_CLAVE")
                keyAlias = System.getenv("FIRMA_ALIAS") ?: "servimaxx"
                keyPassword = System.getenv("FIRMA_CLAVE")
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            signingConfig = signingConfigs.findByName("permanente") ?: signingConfigs.getByName("debug")
        }
    }

    buildFeatures {
        buildConfig = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.activity:activity-ktx:1.9.3")
    implementation("androidx.swiperefreshlayout:swiperefreshlayout:1.1.0")
    implementation("androidx.core:core-splashscreen:1.0.1")
}
