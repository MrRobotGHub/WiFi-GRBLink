<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Control Manual del Láser</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, sans-serif; background-color: #f0f0f0; margin: 0; padding: 0; }
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
h1 { text-align: center; margin-top: 20px; }
.panel { max-width: 500px; margin: 20px auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 0 10px #ccc; }
.section { margin-bottom: 20px; }
input[type=number], input[type=range] { width: 80px; }
.cruceta {
  display: grid;
  grid-template-columns: 70px 70px 70px;
  grid-template-rows: 70px 70px 70px;
  gap: 2px;
  justify-content: center;
  align-items: center;
}
.cruceta button {
  width: 100%;
  height: 100%;
  font-size: 24px;
  border-radius: 8px;
  cursor: pointer;
}
button.active { background-color: #4CAF50; color: white; }
#powerButtons { display: flex; justify-content: space-around; margin-top: 10px; }
#powerButtons button { flex: 1; margin: 0 2px; }
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

<h1>Control Manual del Láser</h1>

<div class="panel">

<div class="section">
<h2>Área de Trabajo</h2>
X Máximo: <input type="number" id="maxX" value="" placeholder="Requerido"> mm<br><br>
Y Máximo: <input type="number" id="maxY" value="" placeholder="Requerido"> mm<br><br>
<button onclick="aplicarArea()">✅ Aplicar Área</button>
</div>

<div class="section">
<h2>Control de Movimiento</h2>
<div class="cruceta">
<div></div>
<button onclick="move('y+', this)">⬆️</button>
<div></div>
<button onclick="move('x-', this)">⬅️</button>
<button onclick="goHomeFisico()">🏠</button>
<button onclick="move('x+', this)">➡️</button>
<div></div>
<button onclick="move('y-', this)">⬇️</button>
<div></div>
</div><br>
Distancia:
<input type="range" id="distSlider" min="0.1" max="1000" step="0.1" value="5" oninput="syncDistInput(this.value)">
<input type="number" id="distInput" value="5" min="0.1" max="1000" step="0.1" onchange="syncDistSlider(this.value)"> mm
<br><br>
Velocidad XY:
<input type="number" id="velocidadXY" value="4000" min="1" max="6000"> mm/min
</div>

<div class="section">
<h2>Potencia del Láser</h2>
<div id="powerButtons">
<button onclick="setPower(1, this)" class="active">1%</button>
<button onclick="setPower(5, this)">5%</button>
<button onclick="setPower(10, this)">10%</button>
<button onclick="setPower(15, this)">15%</button>
<button onclick="setPower(20, this)">20%</button>
</div><br>
Manual:
<input type="number" id="powerManual" value="1" min="1" max="20" onchange="validarPotencia()"> %
<button onclick="applyManualPower()">Aplicar</button><br><br>
<button onclick="toggleLaser(this)">🔥 Encender / Apagar Láser</button>
</div>

<div class="section">
<h2>Posición Actual</h2>
X: <span id="posX">0</span> mm -
Y: <span id="posY">0</span> mm -
Z: <span id="posZ">0</span> mm
</div>

</div>

<script>
// Tu script original intacto (aplicarArea, move, home, toggleLaser, setPower, updatePos, etc.)

let posX = 0, posY = 0, posZ = 0;
let laserOn = false;
let areaSet = false;
let maxX = null, maxY = null;
let currentPower = 1;
let maxPower = 1000; // Será actualizado si es posible

// Al cargar la página, intentar leer $30
fetch('grbl_send.php?action=read_max_power')
  .then(response => response.text())
  .then(data => {
    const value = parseInt(data.trim());
    if (!isNaN(value)) {
      maxPower = value;
      console.log('Máxima potencia GRBL detectada:', maxPower);
    } else {
      console.warn('⚠️ No se pudo leer $30. Usando 1000 como predeterminado.');
    }
  })
  .catch(() => {
    console.warn('⚠️ Error al intentar leer $30. Usando 1000 como predeterminado.');
  });

function aplicarArea() {
  maxX = parseFloat(document.getElementById('maxX').value);
  maxY = parseFloat(document.getElementById('maxY').value);
  if (!maxX || !maxY || maxX <= 0 || maxY <= 0) {
    alert('⚠️ Debes ingresar un área de trabajo válida para evitar daños.');
    return;
  }
  areaSet = true;
  alert('Área aplicada. Haciendo Home automático...');
  setTimeout(goHomeFisico, 500);
}

function goHomeFisico() {
  fetch('grbl_send.php?action=home').then(() => {
    posX = 0; posY = 0; posZ = 0;
    updatePos();
  });
}

function move(dir, btn) {
  if (!areaSet) {
    alert('⚠️ Primero debes aplicar el área de trabajo por seguridad.');
    return;
  }
  let distancia = parseFloat(document.getElementById('distInput').value);
  let velocidadXY = parseFloat(document.getElementById('velocidadXY').value);

  let dx = 0, dy = 0;
  if (dir === 'x+') dx = distancia;
  if (dir === 'x-') dx = -distancia;
  if (dir === 'y+') dy = distancia;
  if (dir === 'y-') dy = -distancia;

  let futureX = posX + dx;
  let futureY = posY + dy;

  if (futureX < 0) dx = -posX;
  if (futureX > maxX) dx = maxX - posX;
  if (futureY < 0) dy = -posY;
  if (futureY > maxY) dy = maxY - posY;

  if (dx !== 0 || dy !== 0) {
    btn.disabled = true;
    btn.classList.add('active');
    fetch(`grbl_send.php?action=move&dx=${dx}&dy=${dy}&dz=0&vxy=${velocidadXY}&maxX=${maxX}&maxY=${maxY}`)
    .then(() => {
      posX += dx;
      posY += dy;
      updatePos();
      setTimeout(() => {
        btn.disabled = false;
        btn.classList.remove('active');
      }, 300);
    });
  }
}

function setPower(p, btn) {
  currentPower = p;
  document.getElementById('powerManual').value = p;
  highlightPowerButton(btn);
}

function applyManualPower() {
  const input = document.getElementById('powerManual');
  let p = parseInt(input.value);
  if (p > 20) {
    alert("La potencia máxima permitida es 20%");
    p = 20;
    input.value = 20;
  }
  currentPower = p;
  highlightPowerButton(null);
}

function validarPotencia() {
  const input = document.getElementById('powerManual');
  if (parseInt(input.value) > 20) {
    alert("La potencia máxima permitida es 20%");
    input.value = 20;
  }
}

function toggleLaser(btn) {
  const potencia = parseInt(document.getElementById('powerManual').value);
  if (potencia > 20) {
    alert("La potencia máxima permitida es 20%");
    return;
  }
  if (!laserOn && potencia > 5 && !confirm("⚠️ Vas a activar el láser con más del 5%. ¿Estás seguro?")) return;

  laserOn = !laserOn;
  btn.classList.toggle('active', laserOn);

  if (laserOn) {
    fetch(`grbl_send.php?action=laser_on&power=${potencia}`);
  } else {
    fetch('grbl_send.php?action=laser_off');
  }
}

function updatePos() {
  document.getElementById('posX').innerText = posX.toFixed(1);
  document.getElementById('posY').innerText = posY.toFixed(1);
  document.getElementById('posZ').innerText = posZ.toFixed(1);
}

function syncDistInput(val) {
  document.getElementById('distInput').value = val;
}
function syncDistSlider(val) {
  document.getElementById('distSlider').value = val;
}

function highlightPowerButton(activeBtn) {
  document.querySelectorAll('#powerButtons button').forEach(b => b.classList.remove('active'));
  if (activeBtn) activeBtn.classList.add('active');
}
</script>

<footer>
  🚀 Hecho por <strong><a href="https://www.instagram.com/alanherbert/" target="_blank" style="color: inherit; text-decoration: underline;">Mr Robot</a></strong> con ❤️ para el mundo.
</footer>

</body>
</html>
