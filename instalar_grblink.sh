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

echo "✅ Ajustando permisos para acceso a /dev/ttyUSB0..."

# Asegurar que www-data esté en el grupo 'dialout' para acceso serial
usermod -a -G dialout www-data

# Opcional pero recomendado: Instalar 'stty' si no está
apt-get install -y coreutils

echo "✅ Permisos configurados para acceso serial."

# ---------------------------
# Corrección moderna: Servicio para asegurar /dev/vcio
# ---------------------------

echo "🚀 Configurando servicio grblink-vcio..."

sudo bash -c 'cat > /etc/systemd/system/grblink-vcio.service <<EOF
[Unit]
Description=Crear dispositivo /dev/vcio para medición de temperatura
DefaultDependencies=no
After=sysinit.target local-fs.target

[Service]
Type=oneshot
ExecStart=/bin/bash -c '\''if [ ! -e /dev/vcio ]; then mknod /dev/vcio c 100 0; chmod 666 /dev/vcio; fi'\'
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF'

# Recargar systemd y habilitar servicio nuevo
sudo systemctl daemon-reload
sudo systemctl enable grblink-vcio.service
sudo systemctl start grblink-vcio.service

# Habilitar servicios principales
sudo systemctl enable ser2net
sudo systemctl restart ser2net
sudo systemctl restart apache2

echo "✅ Instalación completada. Accede desde tu navegador en http://<IP-de-tu-Raspberry>"
