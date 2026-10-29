package cl.servimaxx.monitoreo

import android.annotation.SuppressLint
import android.app.DownloadManager
import android.content.ActivityNotFoundException
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.view.View
import android.webkit.CookieManager
import android.webkit.URLUtil
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout

/**
 * App de SERVIMAXX Monitoreo: abre la plataforma web (servimaxx.cl/monitoreo)
 * a pantalla completa, guarda la sesión y maneja descargas CSV y la falta de internet.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var web: WebView
    private lateinit var refrescar: SwipeRefreshLayout
    private lateinit var progreso: ProgressBar
    private lateinit var error: LinearLayout
    private var cargaConError = false

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        web = findViewById(R.id.web)
        refrescar = findViewById(R.id.refrescar)
        progreso = findViewById(R.id.progreso)
        error = findViewById(R.id.error)

        CookieManager.getInstance().setAcceptCookie(true)

        web.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            loadWithOverviewMode = true
            useWideViewPort = true
            builtInZoomControls = true
            displayZoomControls = false
            mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            userAgentString = "$userAgentString ServimaxxApp/${BuildConfig.VERSION_NAME}"
        }
        web.webViewClient = Cliente()
        web.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView, newProgress: Int) {
                progreso.progress = newProgress
                progreso.visibility = if (newProgress < 100) View.VISIBLE else View.GONE
            }
        }
        web.setDownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
            descargar(url, userAgent, contentDisposition, mimeType)
        }

        // Tirar hacia abajo recarga, solo si la página ya está arriba del todo.
        refrescar.setColorSchemeResources(R.color.acento)
        refrescar.setOnChildScrollUpCallback { _, _ -> web.scrollY > 0 }
        refrescar.setOnRefreshListener { web.reload() }

        findViewById<Button>(R.id.reintentar).setOnClickListener {
            error.visibility = View.GONE
            web.reload()
        }

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (web.canGoBack()) web.goBack() else finish()
            }
        })

        if (savedInstanceState == null || web.restoreState(savedInstanceState) == null) {
            web.loadUrl(urlInicial(intent))
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        // Un enlace de la plataforma tocado en un correo de alarma abre esa página.
        intent.data?.let { if (esDeLaPlataforma(it)) web.loadUrl(it.toString()) }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        web.saveState(outState)
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    private fun urlInicial(intent: Intent?): String {
        val data = intent?.data
        return if (data != null && esDeLaPlataforma(data)) data.toString() else BuildConfig.URL_INICIO
    }

    private fun esDeLaPlataforma(uri: Uri): Boolean {
        val host = uri.host ?: return false
        return uri.scheme == "https" && (host == BuildConfig.DOMINIO || host.endsWith("." + BuildConfig.DOMINIO))
    }

    private fun abrirFuera(uri: Uri) {
        try {
            startActivity(Intent(Intent.ACTION_VIEW, uri))
        } catch (e: ActivityNotFoundException) {
            Toast.makeText(this, uri.toString(), Toast.LENGTH_LONG).show()
        }
    }

    private fun descargar(url: String, userAgent: String, contentDisposition: String?, mimeType: String?) {
        try {
            val nombre = URLUtil.guessFileName(url, contentDisposition, mimeType)
            val pedido = DownloadManager.Request(Uri.parse(url)).apply {
                // La descarga del CSV necesita la sesión del usuario.
                addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url) ?: "")
                addRequestHeader("User-Agent", userAgent)
                setMimeType(mimeType)
                setTitle(nombre)
                setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                // Desde Android 10 la carpeta Descargas no necesita permiso; antes se guarda en la carpeta de la app.
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, nombre)
                } else {
                    setDestinationInExternalFilesDir(this@MainActivity, Environment.DIRECTORY_DOWNLOADS, nombre)
                }
            }
            (getSystemService(DOWNLOAD_SERVICE) as DownloadManager).enqueue(pedido)
            Toast.makeText(this, getString(R.string.descargando, nombre), Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Toast.makeText(this, R.string.descarga_error, Toast.LENGTH_LONG).show()
        }
    }

    private inner class Cliente : WebViewClient() {
        override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
            val uri = request.url
            if (esDeLaPlataforma(uri)) return false
            // Mapas, WhatsApp, teléfono, correo y otros sitios se abren en su propia app.
            abrirFuera(uri)
            return true
        }

        override fun onPageStarted(view: WebView, url: String, favicon: Bitmap?) {
            cargaConError = false
        }

        override fun onPageFinished(view: WebView, url: String) {
            refrescar.isRefreshing = false
            if (!cargaConError) error.visibility = View.GONE
            CookieManager.getInstance().flush()
        }

        override fun onReceivedError(view: WebView, request: WebResourceRequest, err: WebResourceError) {
            if (request.isForMainFrame) {
                cargaConError = true
                refrescar.isRefreshing = false
                error.visibility = View.VISIBLE
            }
        }
    }
}
