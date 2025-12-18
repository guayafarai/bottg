<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * GENERADOR DE CÓDIGOS QR PARA YAPE/PLIN
 * ═══════════════════════════════════════════════════════════════
 * 
 * Genera códigos QR para pagos rápidos con Yape
 * 
 */

class GeneradorQR {
    
    /**
     * Genera un código QR para Yape
     * 
     * @param string $numero Número de teléfono
     * @param float $monto Monto a pagar
     * @param string $mensaje Mensaje opcional
     * @return string URL del código QR generado
     */
    public static function generarQRYape($numero, $monto, $mensaje = '') {
        // Limpiar número (solo dígitos)
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
        // URL de API de Google Charts para QR
        $baseUrl = 'https://chart.googleapis.com/chart';
        
        // Formatear datos de Yape
        // Formato: yape://pay?phone=NUMERO&amount=MONTO&message=MENSAJE
        $yapeDatos = "yape://pay?phone={$numero}";
        
        if ($monto > 0) {
            $yapeDatos .= "&amount=" . number_format($monto, 2, '.', '');
        }
        
        if (!empty($mensaje)) {
            $yapeDatos .= "&message=" . urlencode($mensaje);
        }
        
        // Parámetros para el QR
        $params = [
            'cht' => 'qr',           // Tipo: QR code
            'chs' => '300x300',      // Tamaño: 300x300
            'chl' => $yapeDatos,     // Datos del QR
            'choe' => 'UTF-8'        // Encoding
        ];
        
        $url = $baseUrl . '?' . http_build_query($params);
        
        return $url;
    }
    
    /**
     * Genera un código QR genérico (para número de teléfono)
     * 
     * @param string $numero Número de teléfono
     * @param string $nombre Nombre del destinatario
     * @param float $monto Monto (opcional)
     * @return string URL del código QR generado
     */
    public static function generarQRSimple($numero, $nombre = '', $monto = 0) {
        $baseUrl = 'https://chart.googleapis.com/chart';
        
        // Limpiar número
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
        // Construir texto del QR
        $texto = "TEL:{$numero}";
        
        if (!empty($nombre)) {
            $texto = "{$nombre}\n{$texto}";
        }
        
        if ($monto > 0) {
            $texto .= "\nMonto: S/ " . number_format($monto, 2);
        }
        
        $params = [
            'cht' => 'qr',
            'chs' => '300x300',
            'chl' => $texto,
            'choe' => 'UTF-8'
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * Genera QR con API de QR Code Monkey (alternativa más estilizada)
     * 
     * @param string $datos Datos a codificar
     * @return string URL del código QR
     */
    public static function generarQRAvanzado($datos) {
        // Esta es una alternativa usando otra API
        // Puedes personalizar colores, logos, etc.
        
        $baseUrl = 'https://api.qrserver.com/v1/create-qr-code/';
        
        $params = [
            'size' => '300x300',
            'data' => $datos,
            'bgcolor' => 'FFFFFF',
            'color' => '741874',  // Color morado de Yape
            'qzone' => 1,
            'format' => 'png'
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * Descarga y guarda un QR localmente
     * 
     * @param string $url URL del QR
     * @param string $nombreArchivo Nombre del archivo a guardar
     * @return bool|string Ruta del archivo o false si falla
     */
    public static function descargarQR($url, $nombreArchivo = null) {
        // Crear directorio si no existe
        $dir = __DIR__ . '/qr_codes/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Generar nombre si no se proporciona
        if ($nombreArchivo === null) {
            $nombreArchivo = 'qr_' . time() . '_' . rand(1000, 9999) . '.png';
        }
        
        $rutaArchivo = $dir . $nombreArchivo;
        
        // Descargar imagen
        $contenido = @file_get_contents($url);
        
        if ($contenido === false) {
            return false;
        }
        
        // Guardar archivo
        if (file_put_contents($rutaArchivo, $contenido)) {
            return $rutaArchivo;
        }
        
        return false;
    }
    
    /**
     * Genera un QR temporal para una orden específica
     * 
     * @param array $orden Datos de la orden
     * @return string URL del QR
     */
    public static function generarQROrden($orden) {
        $numero = PAGO_YAPE_NUMERO;
        $monto = $orden['monto'];
        $codigo = $orden['codigo_orden'];
        
        // Mensaje con código de orden
        $mensaje = "Orden: {$codigo}";
        
        return self::generarQRYape($numero, $monto, $mensaje);
    }
}

// ═══════════════════════════════════════════════════════════════
// EJEMPLO DE USO
// ═══════════════════════════════════════════════════════════════

/*
// Generar QR para Yape
$qrUrl = GeneradorQR::generarQRYape('933158015', 10.00, 'Orden: ORD-123');

// Generar QR simple
$qrUrl = GeneradorQR::generarQRSimple('933158015', 'F4 Mobile', 10.00);

// Generar y descargar QR
$qrUrl = GeneradorQR::generarQRYape('933158015', 10.00);
$rutaArchivo = GeneradorQR::descargarQR($qrUrl, 'pago_orden_123.png');

// Usar en Telegram
enviarFoto($chatId, $qrUrl, "Escanea este QR con Yape");
*/

?>
