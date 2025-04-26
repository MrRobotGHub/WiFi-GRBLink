#!/bin/bash

echo "🚀 Instalando WiFi GRBLink Panel con control GRBL..."

# Actualizar sistema y dependencias
sudo apt update
sudo apt install -y apache2 php unzip ser2net

# Eliminar index.html por defecto de Apache
sudo rm -f /var/www/html/index.html

# Copiar archivos del panel
sudo cp index.php /var/www/html/
sudo cp ping.php /var/www/html/
sudo cp control_laser.php /var/www/html/
sudo cp grbl_send.php /var/www/html/

# Aplicar permisos
sudo chmod 644 /var/www/html/*.php
sudo chown www-data:www-data /var/www/html/*.php

# Agregar www-data al grupo dialout para acceso serial
sudo usermod -aG dialout www-data

# Configurar ser2net
sudo bash -c 'cat > /etc/ser2net.yaml <<EOF
connection: &s30
  accepter: tcp,23
  connector: serialdev,/dev/ttyUSB0,115200n81,local
EOF'

# Configurar rc.local si no existe
if [ ! -f /etc/rc.local ]; then
sudo bash -c 'cat > /etc/rc.local <<EOF
#!/bin/bash
[ ! -e /dev/vcio ] && mknod /dev/vcio c 100 0
chmod 666 /dev/vcio
exit 0
EOF'
fi
sudo chmod +x /etc/rc.local

# Servicio para rc-local
sudo bash -c 'cat > /etc/systemd/system/rc-local.service <<EOF
[Unit]
Description=/etc/rc.local Compatibility
ConditionPathExists=/etc/rc.local

[Service]
Type=forking
ExecStart=/etc/rc.local start
TimeoutSec=0
StandardOutput=journal+console
RemainAfterExit=yes
GuessMainPID=no

[Install]
WantedBy=multi-user.target
EOF'

# ---------------------------
# Agregado para Control Manual del Láser
# ---------------------------

echo "✅ Ajustando permisos para acceso a /dev/ttyUSB0..."

# Asegurar que www-data esté en el grupo 'dialout' para acceso serial
usermod -a -G dialout www-data

# Opcional pero recomendado: Instalar 'stty' si no está
apt-get install -y coreutils

# Crear reglas UDEV opcionales si quieres asegurar que siempre sea /dev/ttyUSB0
# (esto lo podemos hacer si en pruebas ves que cambia a ttyUSB1, ttyUSB2, etc.)
# Por ahora no es obligatorio.

echo "✅ Permisos configurados para acceso serial."



# ---------------------------
# Fin de agregado
# ---------------------------


# Habilitar servicios
sudo systemctl enable rc-local
sudo systemctl start rc-local
sudo systemctl enable ser2net
sudo systemctl restart ser2net
sudo systemctl restart apache2

echo "✅ Instalación completada. Accede desde tu navegador en http://<IP-de-tu-Raspberry>"
