<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reboot') {
        echo '<!DOCTYPE html>
        <html lang="es"><head><meta charset="UTF-8"><title>Reiniciando...</title>
        <style>body{background:#000;color:#0f0;font-family:monospace;text-align:center;padding-top:100px}
        .warn{color:#f80}h1{color:#6df}</style></head><body>
        <h1>Reiniciando el sistema<span id="dots"></span></h1>
        <p class="warn">Esto puede tardar hasta 2 minutos. No cierres esta página.</p>
        <p>Reconectando automáticamente...</p>
        <script>
        function intentarReconectar() {
            fetch("ping.php", { cache: "no-store" })
                .then(res => { if (res.ok) location.href = "index.php?rebooted=true"; })
                .catch(() => setTimeout(intentarReconectar, 3000));
        }
        setTimeout(intentarReconectar, 5000);
        let dots = 0;
        setInterval(() => {
            dots = (dots + 1) % 4;
            document.getElementById("dots").textContent = ".".repeat(dots);
        }, 500);
        </script></body></html>';
        flush();
        shell_exec("sleep 1 && sudo reboot");
        exit;
    } elseif ($_POST['action'] === 'restart_ser2net') {
        shell_exec("sudo systemctl restart ser2net");
        header("Location: index.php?restarted=true");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WiFi GRBLink</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f0f0f0;
      margin: 0;
      padding: 0;
      color: #333;
    }
    header {
      background: #ffffff;
      padding: 15px;
      text-align: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    nav a {
      margin: 0 15px;
      text-decoration: none;
      color: #333;
      font-weight: bold;
    }
    main {
      padding: 20px;
      max-width: 900px;
      margin: auto;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 0 10px #ccc;
      margin-top: 20px;
    }
    pre {
      background: #f5f5f5;
      padding: 10px;
      border-radius: 8px;
      overflow-x: auto;
    }
    .botones {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    button.action {
      padding: 10px 20px;
      background: #333;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: bold;
      min-width: 200px;
    }
    button.action:hover {
      background: #4CAF50;
      color: white;
    }
    footer {
      margin-top: 40px;
      text-align: center;
      font-size: 0.9em;
      color: #888;
      padding-bottom: 20px;
    }
    footer a {
      color: inherit;
      text-decoration: underline;
    }
  </style>
</head>
<body>

<header>
    <nav>
        <a href="index.php">🏠 Inicio</a> |
        <a href="control_laser.php">🎯 Control Láser</a>
    </nav>
</header>

<main>

    <h2>📡 IP Local:</h2>
    <pre><?php echo $_SERVER['SERVER_ADDR']; ?></pre>

    <h2>📶 Red Wi-Fi Conectada:</h2>
    <pre><?php
    $ssid = trim(shell_exec("iwgetid -r 2>/dev/null"));
    echo $ssid ? $ssid : "⚠️ No conectado a Wi-Fi";
    ?></pre>

    <h2>📟 Estado del Servicio Ser2Net:</h2>
    <pre><?php system("systemctl is-active ser2net"); ?></pre>

    <h2>🧵 Dispositivo Detectado:</h2>
    <pre><?php
    $puerto = trim(shell_exec("ls /dev/ttyUSB* 2>/dev/null"));
    if ($puerto) {
        echo "$puerto\n";
        $info = shell_exec("udevadm info -q property -n $puerto 2>/dev/null");
        if (preg_match('/ID_MODEL=(.*)/', $info, $model)) echo "Modelo: " . $model[1] . "\n";
        if (preg_match('/ID_VENDOR=(.*)/', $info, $vendor)) echo "Fabricante: " . $vendor[1];
    } else {
        echo "⚠️ No se detecta ningún dispositivo conectado al puerto USB.";
    }
    ?></pre>

    <h2>🌡️ Temperatura CPU:</h2>
    <pre><?php
    $temp = shell_exec("vcgencmd measure_temp 2>/dev/null");
    echo $temp ? str_replace("temp=", "", trim($temp)) : "⚠️ No disponible (verifica /dev/vcio y vcgencmd)";
    ?></pre>

    <h2>🧠 Rendimiento:</h2>
    <pre><?php
    $cpu = shell_exec("top -bn1 | grep 'Cpu(s)'");
    preg_match('/(\d+\.\d+)\s*id/', $cpu, $matches);
    $cpuUsed = 100 - floatval($matches[1]);
    $ram = shell_exec("free | grep Mem | awk '{print \$3/\$2 * 100.0}'");
    $ramUsed = round(floatval($ram));
    echo "CPU: " . round($cpuUsed) . "% | RAM: " . $ramUsed . "%";
    ?></pre>

    <h2>📶 Señal Wi-Fi:</h2>
    <pre><?php
    $wifi = shell_exec("iwconfig wlan0 2>/dev/null");
    preg_match('/Signal level=-([0-9]+) dBm/', $wifi, $match);
    if (isset($match[1])) {
        $signal = intval($match[1]);
        $quality = max(0, min(100, 2 * ($signal + 100)));
        if ($signal <= 50) $nivel = "📶 Excelente";
        elseif ($signal <= 60) $nivel = "📶 Buena";
        elseif ($signal <= 70) $nivel = "📶 Regular";
        else $nivel = "📶 Mala";
        echo "$nivel ({$quality}%)";
    } else {
        echo "No disponible";
    }
    ?></pre>

    <form method="post" class="botones">
      <button name="action" value="restart_ser2net" class="action">🔄 Reiniciar servicio Ser2Net</button>
      <button name="action" value="reboot" class="action">🔁 Reiniciar Raspberry</button>
    </form>

</main>

<footer>
🚀 Hecho por <strong><a href="https://www.instagram.com/alanherbert/" target="_blank">Mr Robot</a></strong> con ❤️ para el mundo.
</footer>

</body>
</html>
