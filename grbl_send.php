<?php
session_start();

// Variables de sesión iniciales
if (!isset($_SESSION['posX'])) $_SESSION['posX'] = 0;
if (!isset($_SESSION['posY'])) $_SESSION['posY'] = 0;
if (!isset($_SESSION['posZ'])) $_SESSION['posZ'] = 0;
if (!isset($_SESSION['power'])) $_SESSION['power'] = 1;
if (!isset($_SESSION['maxPower'])) $_SESSION['maxPower'] = 1000;

$serialPort = '/dev/ttyUSB0';
$baudRate = '115200';

$fp = @fopen($serialPort, 'w+');
if (!$fp) {
    http_response_code(500);
    echo "No se pudo abrir el puerto serial.";
    exit;
}

stream_set_blocking($fp, true);
exec("stty -F $serialPort $baudRate raw -echo");

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'home':
        fwrite($fp, "\$H\n");
        $_SESSION['posX'] = 0;
        $_SESSION['posY'] = 0;
        $_SESSION['posZ'] = 0;
        break;

    case 'move':
        $dx = floatval($_GET['dx'] ?? 0);
        $dy = floatval($_GET['dy'] ?? 0);
        $dz = floatval($_GET['dz'] ?? 0);
        $vxy = intval($_GET['vxy'] ?? 4000);
        $vz = intval($_GET['vz'] ?? 600);
        $maxX = floatval($_GET['maxX'] ?? 360);
        $maxY = floatval($_GET['maxY'] ?? 360);

        $targetX = $_SESSION['posX'] + $dx;
        $targetY = $_SESSION['posY'] + $dy;
        $targetZ = $_SESSION['posZ'] + $dz;

        if ($targetX < 0) $dx = -$_SESSION['posX'];
        if ($targetX > $maxX) $dx = $maxX - $_SESSION['posX'];
        if ($targetY < 0) $dy = -$_SESSION['posY'];
        if ($targetY > $maxY) $dy = $maxY - $_SESSION['posY'];

        $_SESSION['posX'] += $dx;
        $_SESSION['posY'] += $dy;
        $_SESSION['posZ'] += $dz;

        fwrite($fp, "G91\n");
        if ($dz != 0) {
            fwrite($fp, sprintf("G1 Z%.2f F%d\n", $dz, $vz));
        } else {
            fwrite($fp, sprintf("G1 X%.2f Y%.2f F%d\n", $dx, $dy, $vxy));
        }
        fwrite($fp, "G90\n");
        break;

    case 'laser_on':
        $power = intval($_GET['power'] ?? $_SESSION['power']);
        if ($power > 20) $power = 20;
        $_SESSION['power'] = $power;
        $scaledPower = intval(($power / 100) * ($_SESSION['maxPower'] ?? 1000));
        fwrite($fp, "M3 S$scaledPower\n");
        break;

    case 'laser_off':
        fwrite($fp, "M5\n");
        break;

    case 'set_power':
        $power = intval($_GET['power'] ?? 1);
        if ($power > 20) $power = 20;
        $_SESSION['power'] = $power;
        break;

    case 'read_max_power':
        fwrite($fp, "$$\n");
        usleep(500000); // medio segundo de espera para recibir datos

        $output = '';
        $start = microtime(true);
        while ((microtime(true) - $start) < 2) { // Leer durante 2 segundos
            $line = fgets($fp);
            if ($line === false) break;
            $output .= $line;
            if (strpos($line, 'ok') !== false) break; // Si llega "ok", termina
        }

        if (preg_match('/\\$30=(\\d+)/', $output, $matches)) {
            $_SESSION['maxPower'] = intval($matches[1]);
            echo $_SESSION['maxPower'];
        } else {
            echo "Error";
        }
        fclose($fp);
        exit;

    default:
        http_response_code(400);
        echo "Acción no válida.";
        fclose($fp);
        exit;
}

fclose($fp);
echo "OK";
?>
