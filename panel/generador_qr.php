<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * GENERADOR DE CÓDIGOS QR PARA YAPE/PLIN (CORREGIDO)
 * ═══════════════════════════════════════════════════════════════
 */

class GeneradorQR {
    
    /**
     * Genera un código QR para Yape
     */
    public static function generarQRYape($numero, $monto, $mensaje = '') {
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
        // URL de API de Google Charts para QR
        $baseUrl = 'https://chart.googleapis.com/chart';
        
        // Formato Yape (se puede ajustar según necesidad)
        $yapeDatos = "yape://pay?phone={$numero}";
        
        if ($monto > 0) {
            $yapeDatos .= "&amount=" . number_format($monto, 2, '.', '');
        }
        
        if (!empty($mensaje)) {
            $yapeDatos .= "&message=" . urlencode($mensaje);
        }
        
        $params = [
            'cht' => 'qr',
            'chs' => '300x300',
            'chl' => $yapeDatos,
            'choe' => 'UTF-8'
        ];
        
        $url = $baseUrl . '?' . http_build_query($params);
        
        return $url;
    }
    
    /**
     * Genera un código QR genérico
     */
    public static function generarQRSimple($numero, $nombre = '', $monto = 0) {
        $baseUrl = 'https://chart.googleapis.com/chart';
        
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
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
     * Genera QR con API alternativa (más estilizada)
     */
    public static function generarQRAvanzado($datos) {
        $baseUrl = 'https://api.qrserver.com/v1/create-qr-code/';
        
        $params = [
            'size' => '300x300',
            'data' => $datos,
            'bgcolor' => 'FFFFFF',
            'color' => '741874',
            'qzone' => 1,
            'format' => 'png'
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * Descarga y guarda un QR localmente
     */
    public static function descargarQR($url, $nombreArchivo = null) {
        $dir = sys_get_temp_dir() . '/qr_codes/';
        
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                error_log("Error al crear directorio QR: $dir");
                return false;
            }
        }
        
        if ($nombreArchivo === null) {
            $nombreArchivo = 'qr_' . time() . '_' . rand(1000, 9999) . '.png';
        }
        
        $rutaArchivo = $dir . $nombreArchivo;
        
        $contenido = @file_get_contents($url);
        
        if ($contenido === false) {
            error_log("Error al descargar QR desde: $url");
            return false;
        }
        
        if (file_put_contents($rutaArchivo, $contenido)) {
            return $rutaArchivo;
        }
        
        error_log("Error al guardar QR en: $rutaArchivo");
        return false;
    }
    
    /**
     * Genera un QR temporal para una orden específica
     */
    public static function generarQROrden($orden) {
        // Cargar configuración de pagos si existe
        if (defined('PAGO_YAPE_NUMERO')) {
            $numero = PAGO_YAPE_NUMERO;
        } else {
            $numero = '924780239'; // Por defecto
        }
        
        $monto = $orden['monto'];
        $codigo = $orden['codigo_orden'];
        
        $mensaje = "Orden: {$codigo}";
        
        return self::generarQRYape($numero, $monto, $mensaje);
    }
    
    /**
     * Verifica si una URL de QR es válida
     */
    public static function verificarQR($url) {
        $headers = @get_headers($url);
        return $headers && strpos($headers[0], '200') !== false;
    }
    
    /**
     * Genera QR con reintentos en caso de fallo
     */
    public static function generarQRConReintentos($numero, $monto, $mensaje = '', $intentos = 3) {
        for ($i = 0; $i < $intentos; $i++) {
            try {
                $url = self::generarQRYape($numero, $monto, $mensaje);
                
                if (self::verificarQR($url)) {
                    return $url;
                }
                
                // Intentar con método alternativo
                if ($i > 0) {
                    $datos = "Yape: $numero\nMonto: S/ $monto\n$mensaje";
                    $url = self::generarQRAvanzado($datos);
                    
                    if (self::verificarQR($url)) {
                        return $url;
                    }
                }
                
                sleep(1); // Esperar antes de reintentar
                
            } catch (Exception $e) {
                error_log("Error generando QR (intento " . ($i + 1) . "): " . $e->getMessage());
                continue;
            }
        }
        
        // Si todo falla, devolver QR genérico simple
        return self::generarQRSimple($numero, 'Yape', $monto);
    }
    
    /**
     * Obtiene información de un QR
     */
    public static function obtenerInfoQR($url) {
        $info = [
            'url' => $url,
            'valido' => false,
            'tamano' => 0
        ];
        
        $headers = @get_headers($url, 1);
        
        if ($headers && strpos($headers[0], '200') !== false) {
            $info['valido'] = true;
            
            if (isset($headers['Content-Length'])) {
                $info['tamano'] = $headers['Content-Length'];
            }
        }
        
        return $info;
    }
    
    /**
     * Limpia QR codes antiguos del directorio temporal
     */
    public static function limpiarQRsAntiguos($diasAntiguedad = 7) {
        $dir = sys_get_temp_dir() . '/qr_codes/';
        
        if (!is_dir($dir)) {
            return 0;
        }
        
        $eliminados = 0;
        $archivos = glob($dir . 'qr_*.png');
        
        foreach ($archivos as $archivo) {
            $tiempo = filemtime($archivo);
            $edad = (time() - $tiempo) / 86400; // Días
            
            if ($edad > $diasAntiguedad) {
                if (@unlink($archivo)) {
                    $eliminados++;
                }
            }
        }
        
        return $eliminados;
    }
}

?>
