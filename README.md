# Calendario de Recordatorios

Aplicacion web simple para registrar recordatorios en un calendario basico.

## Tecnologias

- PHP 8.3 con Apache
- MySQL 8.4
- Docker y Docker Compose

## Estructura

```text
app/
  Dockerfile
  index.php
  styles.css
db/
  init.sql
.env.example
.gitignore
docker-compose.yml
README.md
informe/
  proyecto.tex
```

## Como iniciar el proyecto

1. Copiar el archivo de variables de entorno:

```bash
cp .env.example .env
```

2. Construir las imagenes:

```bash
docker compose build
```

3. Levantar los contenedores:

```bash
docker compose up -d
```

4. Revisar los contenedores activos:

```bash
docker ps
```

5. Ver logs de la base de datos:

```bash
docker compose logs db
```

6. Abrir la aplicacion en el navegador:

```text
http://localhost:8080
```

## Operaciones CRUD

- Crear recordatorio: formulario para ingresar titulo, fecha, hora y descripcion.
- Leer recordatorios: tabla principal con todos los registros.
- Modificar recordatorio: boton Modificar en cada fila.
- Borrar recordatorio: boton Borrar en cada fila.

## Apagar el proyecto

```bash
docker compose down
```

Para borrar tambien la base de datos persistida:

```bash
docker compose down -v
```
