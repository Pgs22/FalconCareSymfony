# 🦷 FalconCare - Guía Técnica del Proyecto

Este documento detalla el paso a paso de la construcción de FalconCare, una aplicación de gestión dental desarrollada con **Symfony 7** y **PostgreSQL (Neon)**.

## 🛠️ 1. Configuración Inicial del Proyecto

1. **Crear el proyecto:** `symfony new FalconCare`
2. **Entrar a la carpeta:** `cd FalconCare`
3. **Instalar el motor de Base de Datos:** `composer require symfony/orm-pack`
4. **Instalar el generador de código:** `composer require symfony/maker-bundle --dev`
5. **Configuración de XAMPP (PostgreSQL):**
   Es necesario descomentar las siguientes líneas en el `php.ini` para permitir la comunicación con Neon:
   ```ini
   extension=pdo_pgsql
   extension=pgsql

