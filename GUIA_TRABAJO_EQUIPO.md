# Guía de Trabajo en Equipo - Teatro

## 🚀 Configuración Inicial

### Primera vez - Clonar el proyecto

```bash
# Clona el repositorio
git clone https://github.com/DesarrolloMayaton/teatro.git

# Entra a la carpeta
cd teatro

# Cambia a tu rama personal
git checkout [tu-rama]
```

### Asignación de ramas por persona:
- **Moisés Ávila** → `git checkout moises-avila`
- **Moisés Salvador** → `git checkout MOIXKAR`
- **Hugo** → `git checkout hugo`
- **Ulises** → `git checkout ulises`
- **Philip** → `git checkout philip`
- **Posyo** → `git checkout posyo`

## 🌿 Estructura de Ramas

El proyecto tiene las siguientes ramas:

- **main**: Rama principal con código en producción (protegida)
- **develop**: Rama de desarrollo donde se integran todas las características
- **moises-avila**: Rama personal de Moisés Ávila
- **MOIXKAR**: Rama personal de Moisés Salvador
- **hugo**: Rama personal de Hugo
- **ulises**: Rama personal de Ulises
- **philip**: Rama personal de Philip
- **posyo**: Rama personal de Posyo

## 📋 Flujo de Trabajo

### 1. Comenzar a trabajar

Antes de empezar cualquier tarea, asegúrate de tener los últimos cambios:

```bash
# Cambia a tu rama personal
git checkout [tu-rama]

# Actualiza desde develop
git pull origin develop
```

### 2. Trabajar en tu rama

Haz todos tus cambios en tu rama personal:

```bash
# Verifica en qué rama estás
git branch

# Haz tus cambios y guárdalos
git add .
git commit -m "Descripción clara de los cambios"
```

### 3. Subir tus cambios

```bash
# Sube tu rama al repositorio remoto
git push origin [tu-rama]
```

### 4. Integrar tus cambios a develop

Cuando termines una funcionalidad y esté lista para compartir:

**Opción A: Pull Request (Recomendado)**
1. Ve a GitHub
2. Crea un Pull Request desde tu rama hacia `develop`
3. Espera la revisión del equipo
4. Una vez aprobado, haz merge

**Opción B: Merge directo**
```bash
# Cambia a develop
git checkout develop

# Trae los últimos cambios
git pull origin develop

# Integra tu rama
git merge [tu-rama]

# Sube los cambios
git push origin develop
```

## 🚀 Subir Cambios a Main (Producción)

### Proceso para llevar código a main:

**Solo el líder del proyecto o persona designada debe hacer esto:**

```bash
# 1. Asegúrate de que develop esté actualizado y funcional
git checkout develop
git pull origin develop

# 2. Cambia a main
git checkout main
git pull origin main

# 3. Integra develop en main
git merge develop

# 4. Sube los cambios a main
git push origin main
```

### ⚠️ Antes de subir a main, verificar:
- ✅ Todo funciona correctamente en develop
- ✅ No hay errores en el código
- ✅ Se han probado todas las funcionalidades nuevas
- ✅ El equipo está de acuerdo con los cambios

## 🔄 Mantener Todo Actualizado

### Para mantener tu rama local actualizada:

**Rutina diaria recomendada:**

```bash
# 1. Ve a tu rama personal
git checkout [tu-rama]

# 2. Actualiza desde develop (donde están los cambios del equipo)
git pull origin develop

# 3. Si hay conflictos, resuélvelos y haz commit
git add .
git commit -m "Actualiza rama con cambios del equipo"

# 4. Sube tu rama actualizada
git push origin [tu-rama]
```

### Para que tus compañeros tengan todo actualizado:

**Cada compañero debe hacer esto regularmente:**

```bash
# Opción 1: Actualizar solo tu rama
git checkout [tu-rama]
git pull origin develop

# Opción 2: Actualizar todas las ramas locales
git fetch --all
git checkout [tu-rama]
git pull origin develop
```

### 📅 Rutina recomendada para el equipo:

**Al comenzar el día:**
```bash
git checkout [tu-rama]
git pull origin develop
```

**Al terminar el día:**
```bash
git add .
git commit -m "Trabajo del día: [descripción]"
git push origin [tu-rama]
```

**Semanalmente (o cuando sea necesario):**
- Una persona designada sube develop a main
- Todo el equipo actualiza sus ramas desde develop

## ⚠️ Reglas Importantes

1. **NUNCA trabajes directamente en `main`** - Solo se actualiza desde `develop`
2. **Siempre trabaja en tu rama personal** - Evita conflictos con el equipo
3. **Actualiza frecuentemente** - Haz `git pull origin develop` antes de empezar
4. **Commits descriptivos** - Usa mensajes claros: "Añade formulario de login"
5. **Comunica** - Avisa al equipo cuando hagas cambios importantes
6. **Solo una persona sube a main** - Evita conflictos en producción

## 🔄 Comandos Útiles

```bash
# Ver en qué rama estás
git branch

# Cambiar de rama
git checkout [nombre-rama]

# Ver el estado de tus cambios
git status

# Ver historial de commits
git log --oneline

# Descartar cambios locales (¡cuidado!)
git checkout -- .

# Ver diferencias antes de commit
git diff

# Renombrar una rama
git branch -m nombre-viejo nombre-nuevo
git push origin nombre-nuevo
git push origin --delete nombre-viejo
```

## 🔗 Información del Repositorio

- **URL del repositorio**: https://github.com/DesarrolloMayaton/teatro.git
- **Organización**: DesarrolloMayaton

## 🚨 Resolver Conflictos

Si hay conflictos al hacer merge:

1. Git te mostrará los archivos en conflicto
2. Abre los archivos y busca las marcas `<<<<<<<`, `=======`, `>>>>>>>`
3. Edita el archivo dejando el código correcto
4. Guarda los cambios:
```bash
git add .
git commit -m "Resuelve conflictos"
```

## �  Contacto

Si tienes dudas, pregunta al equipo antes de hacer cambios importantes en `develop` o `main`.

---

**¡Buena suerte con el proyecto! 🎭**
