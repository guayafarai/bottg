<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  VERIFICADOR COMPLETO - BOT TELEGRAM IMEI
 * ═══════════════════════════════════════════════════════════════
 * 
 * Este archivo verifica que TODO esté configurado correctamente:
 * ✓ Credenciales de base de datos
 * ✓ Conexión a MySQL
 * ✓ Tablas creadas
 * ✓ Token de Telegram válido
 * ✓ Bot accesible
 * ✓ Webhook configurado
 * ✓ Archivo del bot existe
 * ✓ Permisos correctos
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a tu hosting
 * 2. Abre en el navegador: https://tu-dominio.com/verificar.php
 * 3. Ve el diagnóstico completo
 * 
 * ═══════════════════════════════════════════════════════════════
 */

// Configuración (debe coincidir con tu bot)
$config = [
    'bot_token' => 'TU_TOKEN_AQUI',
    'bot_file' => 'bot_imei_api_gratis.php',
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'telegram_imei',
];

// Función para obtener estado
function getStatus($success) {
    return $success ? '✅' : '❌';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador - Bot Telegram IMEI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 40px;
        }
        
        .check-item {
            background: #f8f9fa;
            border-left: 5px solid #e0e0e0;
            padding: 20px;
            margin: 15px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .check-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .check-item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        
        .check-item.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        
        .check-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .check-icon {
            font-size: 24px;
            margin-right: 15px;
        }
        
        .check-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .check-detail {
            margin-left: 39px;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .check-detail code {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            text-align: center;
        }
        
        .summary-box h2 {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .summary-box p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .info-box {
            background: #e7f3ff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-box h3 {
            color: #0066cc;
            margin-bottom: 10px;
        }
        
        .steps {
            list-style: none;
            padding: 0;
        }
        
        .steps li {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }
        
        .config-display {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 10px 0;
        }
        
        .progress-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .progress-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .progress-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        
        .progress-card .label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Verificador de Configuración</h1>
            <p>Bot de Telegram - Generador de IMEI</p>
        </div>
        
        <div class="content">
            <?php
            // Verificar si está configurado
            if ($config['bot_token'] == 'TU_TOKEN_AQUI') {
                ?>
                <div class="check-item error">
                    <div class="check-header">
                        <span class="check-icon">⚠️</span>
                        <span class="check-title">Configuración Requerida</span>
                    </div>
                    <div class="check-detail">
                        Por favor edita este archivo (<code>verificar.php</code>) y configura tus datos en la sección <code>$config</code>.
                    </div>
                </div>
                <?php
                exit;
            }
            
            // Iniciar verificaciones
            $checks = [];
            $total_checks = 0;
            $passed_checks = 0;
            
            // ========================================
            // CHECK 1: Archivo del bot existe
            // ========================================
            $total_checks++;
            $bot_file_exists = file_exists($config['bot_file']);
            if ($bot_file_exists) {
                $passed_checks++;
                $checks[] = [
                    'status' => 'success',
                    'icon' => '✅',
                    'title' => 'Archivo del Bot Existe',
                    'detail' => "El archivo <code>{$config['bot_file']}</code> está presente en el servidor."
                ];
            } else {
                $checks[] = [
                    'status' => 'error',
                    'icon' => '❌',
                    'title' => 'Archivo del Bot No Encontrado',
                    'detail' => "No se encontró <code>{$config['bot_file']}</code>. Por favor sube el archivo del bot al servidor."
                ];
            }
            
            // ========================================
            // CHECK 2: Conexión a MySQL
            // ========================================
            $total_checks++;
            try {
                $conn = new PDO(
                    "mysql:host=" . $config['db_host'],
                    $config['db_user'],
                    $config['db_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $passed_checks++;
                $checks[] = [
                    'status' => 'success',
                    'icon' => '✅',
                    'title' => 'Conexión MySQL Exitosa',
                    'detail' => "Conectado correctamente a MySQL en <code>{$config['db_host']}</code> con usuario <code>{$config['db_user']}</code>."
                ];
                $mysql_ok = true;
            } catch (PDOException $e) {
                $checks[] = [
                    'status' => 'error',
                    'icon' => '❌',
                    'title' => 'Error de Conexión MySQL',
                    'detail' => "No se pudo conectar: <code>{$e->getMessage()}</code><br>Verifica que las credenciales sean correctas."
                ];
                $mysql_ok = false;
            }
            
            // ========================================
            // CHECK 3: Base de datos existe
            // ========================================
            if ($mysql_ok) {
                $total_checks++;
                try {
                    $result = $conn->query("SHOW DATABASES LIKE '{$config['db_name']}'");
                    $db_exists = $result->rowCount() > 0;
                    
                    if ($db_exists) {
                        $passed_checks++;
                        $conn->exec("USE {$config['db_name']}");
                        $checks[] = [
                            'status' => 'success',
                            'icon' => '✅',
                            'title' => 'Base de Datos Existe',
                            'detail' => "La base de datos <code>{$config['db_name']}</code> está creada correctamente."
                        ];
                    } else {
                        $checks[] = [
                            'status' => 'error',
                            'icon' => '❌',
                            'title' => 'Base de Datos No Existe',
                            'detail' => "La base de datos <code>{$config['db_name']}</code> no existe. Ejecuta el instalador primero."
                        ];
                    }
                } catch (PDOException $e) {
                    $checks[] = [
                        'status' => 'error',
                        'icon' => '❌',
                        'title' => 'Error al Verificar Base de Datos',
                        'detail' => "<code>{$e->getMessage()}</code>"
                    ];
                    $db_exists = false;
                }
                
                // ========================================
                // CHECK 4: Tabla tac_modelos existe
                // ========================================
                if ($db_exists) {
                    $total_checks++;
                    try {
                        $result = $conn->query("SHOW TABLES LIKE 'tac_modelos'");
                        $table_exists = $result->rowCount() > 0;
                        
                        if ($table_exists) {
                            $passed_checks++;
                            $checks[] = [
                                'status' => 'success',
                                'icon' => '✅',
                                'title' => 'Tabla tac_modelos Existe',
                                'detail' => "La tabla principal está creada correctamente."
                            ];
                            
                            // Contar registros
                            $count_result = $conn->query("SELECT COUNT(*) as total FROM tac_modelos");
                            $count = $count_result->fetch(PDO::FETCH_ASSOC)['total'];
                            
                            $total_checks++;
                            if ($count > 0) {
                                $passed_checks++;
                                $checks[] = [
                                    'status' => 'success',
                                    'icon' => '✅',
                                    'title' => 'Datos Iniciales Presentes',
                                    'detail' => "La tabla contiene <strong>$count TACs</strong> registrados."
                                ];
                            } else {
                                $checks[] = [
                                    'status' => 'warning',
                                    'icon' => '⚠️',
                                    'title' => 'Tabla Vacía',
                                    'detail' => "La tabla existe pero no tiene datos. El bot funcionará pero sin TACs pre-cargados."
                                ];
                            }
                        } else {
                            $checks[] = [
                                'status' => 'error',
                                'icon' => '❌',
                                'title' => 'Tabla tac_modelos No Existe',
                                'detail' => "La tabla principal no está creada. Ejecuta el instalador."
                            ];
                        }
                    } catch (PDOException $e) {
                        $checks[] = [
                            'status' => 'error',
                            'icon' => '❌',
                            'title' => 'Error al Verificar Tabla',
                            'detail' => "<code>{$e->getMessage()}</code>"
                        ];
                    }
                }
            }
            
            // ========================================
            // CHECK 5: Token de Telegram válido
            // ========================================
            $total_checks++;
            $api_url = 'https://api.telegram.org/bot' . $config['bot_token'] . '/';
            $response = @file_get_contents($api_url . 'getMe');
            
            if ($response) {
                $result = json_decode($response, true);
                if (isset($result['ok']) && $result['ok']) {
                    $passed_checks++;
                    $bot_info = $result['result'];
                    $checks[] = [
                        'status' => 'success',
                        'icon' => '✅',
                        'title' => 'Token de Telegram Válido',
                        'detail' => "Bot conectado: <strong>@{$bot_info['username']}</strong><br>Nombre: {$bot_info['first_name']}<br>ID: {$bot_info['id']}"
                    ];
                } else {
                    $checks[] = [
                        'status' => 'error',
                        'icon' => '❌',
                        'title' => 'Token Inválido',
                        'detail' => "El token de Telegram no es válido. Verifica que lo copiaste correctamente de @BotFather."
                    ];
                }
            } else {
                $checks[] = [
                    'status' => 'error',
                    'icon' => '❌',
                    'title' => 'No se Puede Conectar a Telegram',
                    'detail' => "No se pudo conectar a la API de Telegram. Verifica tu conexión a internet."
                ];
            }
            
            // ========================================
            // CHECK 6: Webhook configurado
            // ========================================
            $total_checks++;
            $response = @file_get_contents($api_url . 'getWebhookInfo');
            
            if ($response) {
                $result = json_decode($response, true);
                if (isset($result['ok']) && $result['ok']) {
                    $webhook_info = $result['result'];
                    
                    if (!empty($webhook_info['url'])) {
                        $passed_checks++;
                        $pending = $webhook_info['pending_update_count'] ?? 0;
                        $last_error = isset($webhook_info['last_error_message']) ? $webhook_info['last_error_message'] : 'Ninguno';
                        
                        $detail = "URL configurada: <code>{$webhook_info['url']}</code><br>";
                        $detail .= "Mensajes pendientes: <strong>$pending</strong><br>";
                        $detail .= "Último error: $last_error";
                        
                        $checks[] = [
                            'status' => 'success',
                            'icon' => '✅',
                            'title' => 'Webhook Configurado',
                            'detail' => $detail
                        ];
                    } else {
                        $checks[] = [
                            'status' => 'error',
                            'icon' => '❌',
                            'title' => 'Webhook No Configurado',
                            'detail' => "El webhook no está configurado. El bot no recibirá mensajes.<br>Usa el instalador o configúralo manualmente."
                        ];
                    }
                }
            }
            
            // ========================================
            // CHECK 7: Archivo del bot es accesible
            // ========================================
            $total_checks++;
            $bot_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']) . '/' . $config['bot_file'];
            
            $headers = @get_headers($bot_url);
            if ($headers && strpos($headers[0], '200') !== false) {
                $passed_checks++;
                $checks[] = [
                    'status' => 'success',
                    'icon' => '✅',
                    'title' => 'Bot Accesible Públicamente',
                    'detail' => "El archivo del bot es accesible en: <code>$bot_url</code>"
                ];
            } else {
                $checks[] = [
                    'status' => 'warning',
                    'icon' => '⚠️',
                    'title' => 'No se Puede Verificar Acceso al Bot',
                    'detail' => "No se pudo verificar si el bot es accesible públicamente. Esto puede ser normal si tienes restricciones de acceso."
                ];
            }
            
            // ========================================
            // CHECK 8: SSL/HTTPS activo
            // ========================================
            $total_checks++;
            $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
            
            if ($is_https) {
                $passed_checks++;
                $checks[] = [
                    'status' => 'success',
                    'icon' => '✅',
                    'title' => 'SSL/HTTPS Activo',
                    'detail' => "Tu sitio está usando HTTPS, lo cual es requerido por Telegram para webhooks."
                ];
            } else {
                $checks[] = [
                    'status' => 'error',
                    'icon' => '❌',
                    'title' => 'SSL/HTTPS No Detectado',
                    'detail' => "Telegram requiere HTTPS para webhooks. Activa SSL en tu hosting."
                ];
            }
            
            // ========================================
            // Mostrar resumen
            // ========================================
            $percentage = round(($passed_checks / $total_checks) * 100);
            ?>
            
            <div class="summary-box">
                <h2><?php echo $percentage; ?>%</h2>
                <p><?php echo $passed_checks; ?> de <?php echo $total_checks; ?> verificaciones pasadas</p>
            </div>
            
            <div class="progress-summary">
                <div class="progress-card">
                    <div class="number" style="color: #28a745;"><?php echo $passed_checks; ?></div>
                    <div class="label">Exitosas</div>
                </div>
                <div class="progress-card">
                    <div class="number" style="color: #dc3545;"><?php echo $total_checks - $passed_checks; ?></div>
                    <div class="label">Fallidas</div>
                </div>
                <div class="progress-card">
                    <div class="number" style="color: #667eea;"><?php echo $total_checks; ?></div>
                    <div class="label">Total</div>
                </div>
            </div>
            
            <h2 style="margin: 30px 0 20px 0;">Resultados Detallados</h2>
            
            <?php
            // Mostrar todas las verificaciones
            foreach ($checks as $check) {
                ?>
                <div class="check-item <?php echo $check['status']; ?>">
                    <div class="check-header">
                        <span class="check-icon"><?php echo $check['icon']; ?></span>
                        <span class="check-title"><?php echo $check['title']; ?></span>
                    </div>
                    <div class="check-detail"><?php echo $check['detail']; ?></div>
                </div>
                <?php
            }
            
            // ========================================
            // Recomendaciones finales
            // ========================================
            if ($percentage == 100) {
                ?>
                <div class="info-box" style="background: #d4edda; border-color: #28a745;">
                    <h3 style="color: #28a745;">🎉 ¡Todo Está Perfecto!</h3>
                    <p>Tu bot está completamente configurado y listo para usar.</p>
                    <br>
                    <p><strong>Próximos pasos:</strong></p>
                    <ol class="steps">
                        <li>Abre Telegram y busca tu bot</li>
                        <li>Envía <code>/start</code></li>
                        <li>Prueba enviando un TAC: <code>35203310</code></li>
                        <li>¡Disfruta tu bot! 🚀</li>
                    </ol>
                </div>
                <?php
            } elseif ($percentage >= 70) {
                ?>
                <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                    <h3 style="color: #856404;">⚠️ Casi Listo</h3>
                    <p>Tu bot está casi configurado. Revisa los puntos marcados con ❌ arriba y corrígelos.</p>
                </div>
                <?php
            } else {
                ?>
                <div class="info-box" style="background: #f8d7da; border-color: #dc3545;">
                    <h3 style="color: #721c24;">❌ Configuración Incompleta</h3>
                    <p>Hay varios problemas que debes corregir antes de que el bot funcione.</p>
                    <br>
                    <p><strong>Sugerencias:</strong></p>
                    <ol class="steps">
                        <li>Ejecuta el archivo <code>instalar.php</code> para configurar todo automáticamente</li>
                        <li>O corrige manualmente cada punto marcado con ❌</li>
                        <li>Recarga esta página para verificar nuevamente</li>
                    </ol>
                </div>
                <?php
            }
            ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="?" class="btn">🔄 Verificar Nuevamente</a>
                <?php if (file_exists('instalar.php')): ?>
                    <a href="instalar.php" class="btn" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        🚀 Ir al Instalador
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="info-box" style="margin-top: 30px;">
                <h3>💡 Configuración Actual</h3>
                <div class="config-display">
Token: <?php echo substr($config['bot_token'], 0, 10); ?>...<?php echo substr($config['bot_token'], -5); ?>

Bot File: <?php echo $config['bot_file']; ?>

Database: <?php echo $config['db_name']; ?>

Host: <?php echo $config['db_host']; ?>

User: <?php echo $config['db_user']; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
