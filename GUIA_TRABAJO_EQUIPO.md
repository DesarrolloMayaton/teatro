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

## ⚠️ Reglas Importantes

1. **NUNCA trabajes directamente en `main`** - Solo se actualiza desde `develop`
2. **Siempre trabaja en tu rama personal** - Evita conflictos con el equipo
3. **Actualiza frecuentemente** - Haz `git pull origin develop` antes de empezar
4. **Commits descriptivos** - Usa mensajes claros: "Añade formulario de login"
5. **Comunica** - Avisa al equipo cuando hagas cambios importantes

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
