# API de Gestión de Empleados (Laravel + JWT)

Este proyecto consiste en una API RESTful desarrollada con Laravel 12 para la gestión de empleados. Incluye autenticación basada en tokens, control de acceso por roles y está preparada para despliegue en contenedores Docker y orquestación con Kubernetes.

## Tecnologías y Herramientas

- Framework: Laravel 12
- Base de Datos: MySql
- Autenticación: JWT
- Contenerización: Docker & Docker Compose
- Orquestación: Kubernetes (2 réplicas distribuidas)

---

## Instalación y Despliegue Local

1. Clonar el repositorio:
   git clone https://github.com/MadeInRodri/api-laravel-auth

2. Preparar la base de datos:
   php artisan migrate

3. Levantar con Docker Compose:
   docker-compose up -d --build

4. Ejecutar migraciones:
   docker-compose exec app php artisan migrate

---

## Documentación de Endpoints

Nota: Todas las solicitudes deben incluir el Header "Accept: application/json".

### Autenticación

- POST /api/register : Registra un nuevo usuario (Rol default: employee).
- POST /api/login : Inicia sesión y devuelve el Token de acceso.

### Gestión de Usuarios (CRUD)

- GET /api/users : Lista de usuarios (Filtrable con ?role=admin o ?role=employee).
- GET /api/users/{id} : Obtiene datos de un usuario específico.
- POST /api/users : Crea un nuevo usuario/empleado.
- PATCH /api/users/{id} : Actualización parcial de datos.
- DELETE /api/users/{id} : Elimina un usuario de la base de datos.

---

## Estructura de Docker

El entorno utiliza dos contenedores principales:

1. Contenedor 'app': Ejecuta PHP 8.2-FPM con extensiones para MySQL.
2. Contenedor 'web': Servidor Nginx que actúa como proxy inverso.

---

## Equipo

- Rodrigo Alexis Mejía Rivas
- Bryan Josué Fuentes Molina
- Valeria Liseth Paredes Lara
- Leonardo Enrique Flores Coto
- Andre Emanuel Preza Deras
- Joaquín Eduardo Morán Mejía
- Estudiantes de Ingeniería en Ciencias de la Computación
