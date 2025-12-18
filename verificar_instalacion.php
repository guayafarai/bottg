<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * VERIFICADOR AUTOMÁTICO POST-INSTALACIÓN
 * ═══════════════════════════════════════════════════════════════
 * 
 * Este script verifica que todo esté correctamente instalado
 * y funcionando después de aplicar las correcciones.
 * 
 * USO: php verificar_instalacion.php
 *      O visita: https://tu-dominio.com/verificar_instalacion.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class VerificadorInstalacion {
    private $errores = [];
    private $advertencias = [];
    private $exitos = [];
    
    public function ejecutar() {
        echo "\n╔═══════════════════════════════════════════════════════════╗\n";
        echo "║         VERIFICADOR DE INSTALACIÓN - BOT IMEI            ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";
        
        $this->verificarArchivosBasicos();
        $this->verificarConfiguracion();
        $this->verificarBaseDatos();
        $this->verificarPermisos();
        $this->verificarBot();
        $this->verificarSistemaPagos();
        $this->verificarPanel();
        
        $this->mostrarResumen();
    }
    
    private function verificarArchivosBasicos() {
        echo "📁 Verificando archivos básicos...\n";
        
        $archivosRequeridos = [
            'bot_imei_corregido.php' => 'Archivo principal del bot',
            'config_bot.php' => 'Configuración del bot',
            'config_pagos.php' => 'Configuración de pagos',
            'sistema_pagos.php' => 'Sistema de pagos',
            'generador_qr.php' => 'Generador de QR',
            'imeidb_api.php' => 'API de IMEI',
            'config_imeidb.php' => 'Configuración IMEI DB'
        ];
        
        foreach ($archivosRequeridos as $archivo => $descripcion) {
            if (file_exists(__DIR__ . '/' . $archivo)) {
                $this->exito("✅ $archivo - $descripcion");
            } else {
                $this->error("❌ Falta: $archivo - $descripcion");
            }
        }
        
        echo "\n";
    }
    
    private function verificarConfiguracion() {
        echo "⚙️  Verificando configuración...\n";
        
        if (!file_exists(__DIR__ . '/config_bot.php')) {
            $this->error("❌ No existe config_bot.php");
            return;
        }
        
        require_once(__DIR__ . '/config_bot.php');
        
        $constantes = [
            'BOT_TOKEN' => 'Token del bot',
            'DB_HOST' => 'Host de base de datos',
            'DB_NAME' => 'Nombre de base de datos',
            'DB_USER' => 'Usuario de base de datos',
            'DB_PASS' => 'Contraseña de base de datos'
        ];
        
        foreach ($constantes as $constante => $descripcion) {
            if (defined($constante)) {
                $valor = constant($constante);
                if (empty($valor) && $constante != 'DB_PASS') {
                    $this->advertencia("⚠️  $constante está vacío - $descripcion");
                } else {
                    $valorMostrar = $constante == 'BOT_TOKEN' ? substr($valor, 0, 10) . '...' : 
                                   ($constante == 'DB_PASS' ? '***' : $valor);
                    $this->exito("✅ $constante: $valorMostrar");
                }
            } else {
                $this->error("❌ No está definido: $constante");
            }
        }
        
        echo "\n";
    }
    
    private function verificarBaseDatos() {
        echo "🗄️  Verificando base de datos...\n";
        
        if (!defined('DB_HOST')) {
            $this->error("❌ Configuración de BD no disponible");
            return;
        }
        
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $this->exito("✅ Conexión a base de datos exitosa");
            
            // Verificar tablas
            $tablasRequeridas = [
                'usuarios' => 'Tabla de usuarios',
                'ordenes_pago' => 'Órdenes de pago',
                'tac_modelos' => 'Modelos TAC',
                'historial_uso' => 'Historial de uso',
                'transacciones' => 'Transacciones',
                'api_cache' => 'Caché de API'
            ];
            
            foreach ($tablasRequeridas as $tabla => $descripcion) {
                $result = $conn->query("SHOW TABLES LIKE '$tabla'");
                if ($result->rowCount() > 0) {
                    $count = $conn->query("SELECT COUNT(*) as total FROM $tabla")->fetch()['total'];
                    $this->exito("✅ Tabla $tabla existe ($count registros)");
                } else {
                    $this->error("❌ Falta tabla: $tabla - $descripcion");
                }
            }
            
        } catch (PDOException $e) {
            $this->error("❌ Error de BD: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function verificarPermisos() {
        echo "🔐 Verificando permisos...\n";
        
        // Verificar permisos de escritura
        $directorios = [
            sys_get_temp_dir() => 'Directorio temporal del sistema'
        ];
        
        foreach ($directorios as $dir => $descripcion) {
            if (is_writable($dir)) {
                $this->exito("✅ $dir es escribible");
            } else {
                $this->advertencia("⚠️  $dir no es escribible - $descripcion");
            }
        }
        
        echo "\n";
    }
    
    private function verificarBot() {
        echo "🤖 Verificando bot de Telegram...\n";
        
        if (!defined('BOT_TOKEN')) {
            $this->error("❌ BOT_TOKEN no definido");
            return;
        }
        
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getMe';
        $response = @file_get_contents($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['ok']) && $data['ok']) {
                $bot = $data['result'];
                $this->exito("✅ Bot conectado: @{$bot['username']}");
                $this->exito("   Nombre: {$bot['first_name']}");
                $this->exito("   ID: {$bot['id']}");
            } else {
                $this->error("❌ Token de bot inválido");
            }
        } else {
            $this->error("❌ No se puede conectar a Telegram API");
        }
        
        // Verificar webhook
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getWebhookInfo';
        $response = @file_get_contents($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['result'])) {
                $webhook = $data['result'];
                if (!empty($webhook['url'])) {
                    $this->exito("✅ Webhook configurado: " . $webhook['url']);
                    $this->exito("   Mensajes pendientes: " . ($webhook['pending_update_count'] ?? 0));
                } else {
                    $this->advertencia("⚠️  Webhook no configurado");
                }
            }
        }
        
        echo "\n";
    }
    
    private function verificarSistemaPagos() {
        echo "💳 Verificando sistema de pagos...\n";
        
        if (!file_exists(__DIR__ . '/config_pagos.php')) {
            $this->advertencia("⚠️  config_pagos.php no existe");
            return;
        }
        
        require_once(__DIR__ . '/config_pagos.php');
        
        if (defined('PAGO_YAPE_NUMERO')) {
            $this->exito("✅ Yape configurado: " . PAGO_YAPE_NUMERO);
        } else {
            $this->advertencia("⚠️  Yape no configurado");
        }
        
        if (defined('PAGO_PLIN_NUMERO')) {
            $this->exito("✅ Plin configurado: " . PAGO_PLIN_NUMERO);
        } else {
            $this->advertencia("⚠️  Plin no configurado");
        }
        
        echo "\n";
    }
    
    private function verificarPanel() {
        echo "🌐 Verificando panel web...\n";
        
        if (is_dir(__DIR__ . '/panel')) {
            $this->exito("✅ Directorio panel/ existe");
            
            $archivosPanel = [
                'login.php' => 'Página de login',
                'dashboard.php' => 'Dashboard principal',
                'ordenes.php' => 'Gestión de órdenes',
                'config_panel.php' => 'Configuración del panel'
            ];
            
            foreach ($archivosPanel as $archivo => $descripcion) {
                $ruta = __DIR__ . '/panel/' . $archivo;
                if (file_exists($ruta)) {
                    $this->exito("✅ panel/$archivo");
                } else {
                    $this->advertencia("⚠️  Falta: panel/$archivo - $descripcion");
                }
            }
        } else {
            $this->advertencia("⚠️  Directorio panel/ no existe");
        }
        
        echo "\n";
    }
    
    private function exito($mensaje) {
        $this->exitos[] = $mensaje;
        echo "$mensaje\n";
    }
    
    private function error($mensaje) {
        $this->errores[] = $mensaje;
        echo "$mensaje\n";
    }
    
    private function advertencia($mensaje) {
        $this->advertencias[] = $mensaje;
        echo "$mensaje\n";
    }
    
    private function mostrarResumen() {
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║                      RESUMEN FINAL                        ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";
        
        $totalPruebas = count($this->exitos) + count($this->errores) + count($this->advertencias);
        $porcentaje = $totalPruebas > 0 ? round((count($this->exitos) / $totalPruebas) * 100) : 0;
        
        echo "📊 Estadísticas:\n";
        echo "   ✅ Exitosos: " . count($this->exitos) . "\n";
        echo "   ⚠️  Advertencias: " . count($this->advertencias) . "\n";
        echo "   ❌ Errores: " . count($this->errores) . "\n";
        echo "   📈 Porcentaje de éxito: {$porcentaje}%\n\n";
        
        if (count($this->errores) == 0 && count($this->advertencias) == 0) {
            echo "🎉 ¡PERFECTO! Todo está configurado correctamente.\n";
            echo "   Tu bot está listo para usarse.\n\n";
        } elseif (count($this->errores) == 0) {
            echo "✅ BUENO: No hay errores críticos.\n";
            echo "   Hay algunas advertencias pero el bot debería funcionar.\n\n";
        } else {
            echo "⚠️  HAY PROBLEMAS: Se encontraron " . count($this->errores) . " errores.\n";
            echo "   Revisa los errores marcados con ❌ arriba.\n\n";
        }
        
        if (!empty($this->errores)) {
            echo "❌ ERRORES ENCONTRADOS:\n";
            foreach ($this->errores as $error) {
                echo "   $error\n";
            }
            echo "\n";
        }
        
        if (!empty($this->advertencias)) {
            echo "⚠️  ADVERTENCIAS:\n";
            foreach ($this->advertencias as $advertencia) {
                echo "   $advertencia\n";
            }
            echo "\n";
        }
        
        echo "📝 Para más información, lee: INSTRUCCIONES_CORRECCION.md\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
}

// Ejecutar verificador
$verificador = new VerificadorInstalacion();
$verificador->ejecutar();

?>
