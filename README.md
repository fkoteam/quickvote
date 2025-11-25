1-el servidor web usado es httpd

2-los certificados con let's encrypt + certbot, siguiendo las instrucciones de apache + linux snap

3-instalación de mysql

  sudo dnf install mysql-server
  
  sudo systemctl enable --now mysqld
  
  sudo mysql_secure_installation
  
  sudo mysql -u root -p
  
  CREATE USER 'miusuario'@'%' IDENTIFIED BY 'micontrasena';
  
  GRANT ALL PRIVILEGES ON *.* TO 'miusuario'@'%' WITH GRANT OPTION;
  
  FLUSH PRIVILEGES;
  
  EXIT;

  sudo dnf install php-mysqlnd php-json
  
  sudo dnf install epel-release
  
  sudo dnf install phpMyAdmin
  
  sudo vi /etc/httpd/conf.d/phpMyAdmin.conf (Cambia Require local por Require all granted.)
  
  sudo systemctl restart httpd

  Ve a http://TU_IP_ORACLE_LINUX/phpmyadmin

  Para la instalación de phpmyadmin he mirado https://computingforgeeks.com/install-and-configure-phpmyadmin-on-rhel-8/

4-Crea la base de datos vacía en tu servidor MySQL (ej: survey_db).


5-Edita config.php con tus datos reales.

6-Abre el navegador y visita: http://tudominio.com/install.php.
