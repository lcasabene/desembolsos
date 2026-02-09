# Módulo de Presupuesto y Planificación Anual

## Descripción
Módulo completo para la gestión de presupuestos anuales del sistema de gestión de la iglesia. Permite planificar, ejecutar y seguimiento presupuestario con objetivos estratégicos.

## Características Principales

### 🎯 Objetivos Estratégicos
- Definición de objetivos estratégicos anuales
- Documentación completa de metas y visiones
- Integración con la planificación mensual

### 📅 Planificación Mensual
- 12 meses de planificación detallada
- Múltiples actividades por mes
- Asignación de fechas de inicio y fin
- Presupuestos estimados por actividad
- Descripciones detalladas

### 💰 Gestión Financiera
- Cálculo automático de totales
- Seguimiento de ejecución presupuestaria
- Control de saldos disponibles
- Indicadores de porcentaje de ejecución

### 📊 Estados y Flujo de Aprobación
- **Borrador**: Edición libre
- **Enviado para Aprobación**: Esperando revisión
- **Aprobado**: Presupuesto finalizado

### 📈 Reportes y Exportación
- Exportación a Excel completa
- Resumen mensual y anual
- Gráficos de ejecución
- Indicadores visuales

## Estructura de Archivos

```
presupuesto/
├── index.php                  # Listado principal de presupuestos
├── nuevo_presupuesto.php       # Formulario para crear nuevo presupuesto
├── ver_presupuesto.php         # Vista detallada de un presupuesto
├── editar_presupuesto.php      # Edición de presupuesto existente
├── exportar_presupuesto.php   # Exportación a Excel
├── api_aprobar_presupuesto.php # API para aprobación
├── README.md                  # Documentación
└── ../presupuesto_database.sql # Estructura de base de datos
```

## Base de Datos

### Tablas Principales

#### `presupuestos_anuales`
- Información general del presupuesto anual
- Objetivos estratégicos
- Estado del presupuesto
- Control de aprobaciones

#### `presupuesto_mensual_detalle`
- Detalle de actividades por mes
- Fechas y montos
- Descripciones de actividades

#### `ejecucion_presupuestaria`
- Seguimiento de ejecución real
- Montos ejecutados
- Comprobantes y justificaciones

### Vistas

#### `vista_resumen_presupuesto_mensual`
- Resumen consolidado por mes
- Totales y porcentajes de ejecución

#### `vista_resumen_presupuesto_anual`
- Vista completa del presupuesto anual
- Información de usuarios involucrados

## Instalación

1. **Importar la base de datos:**
   ```sql
   mysql -u root -p desembolsos_db < presupuesto_database.sql
   ```

2. **Configurar permisos:**
   - Asegurarse que los usuarios tengan el módulo "Presupuesto" asignado
   - Los administradores pueden aprobar presupuestos

3. **Integración con el menú:**
   - El módulo ya está integrado en `menu_moderno.php`
   - Aparece automáticamente si el usuario tiene permisos

## Uso

### Crear Nuevo Presupuesto
1. Acceder al módulo desde el menú principal
2. Hacer clic en el botón flotante "+"
3. Definir el año y objetivos estratégicos
4. Agregar actividades por mes
5. Establecer montos y fechas
6. Guardar como borrador o enviar para aprobación

### Aprobación de Presupuestos
1. Los administradores ven presupuestos pendientes
2. Pueden revisar detalles y objetivos
3. Aprobar con un solo clic
4. Se genera registro de aprobación

### Seguimiento de Ejecución
1. Visualización de presupuestos aprobados
2. Indicadores de ejecución por actividad
3. Gráficos de progreso mensual
4. Control de saldos disponibles

### Exportación
1. Desde cualquier vista de presupuesto
2. Botón "Exportar Excel"
3. Archivo completo con:
   - Información general
   - Objetivos estratégicos
   - Resumen mensual
   - Detalle completo de actividades

## Características Técnicas

### Frontend
- **Bootstrap 5.3.0**: Framework CSS
- **Bootstrap Icons**: Iconos modernos
- **Chart.js**: Gráficos de ejecución
- **JavaScript vanilla**: Interactividad

### Backend
- **PHP 8+**: Lenguaje principal
- **PDO**: Conexión a base de datos
- **MySQL**: Motor de base de datos
- **UTF-8**: Codificación completa

### Seguridad
- Verificación de autenticación
- Control de permisos por módulo
- Validación de datos
- Prevención de SQL Injection

### Responsive Design
- Adaptado para móviles
- Grid system flexible
- Interfaz táctil optimizada

## Personalización

### Colores y Estilos
Los colores principales están definidos en CSS variables:
```css
--primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
--success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
--warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
```

### Nuevos Estados
Para agregar nuevos estados de presupuesto:
1. Modificar el ENUM en la base de datos
2. Actualizar las clases CSS correspondientes
3. Agregar lógica de validación

### Campos Adicionales
Para agregar nuevos campos a las actividades:
1. Modificar la tabla `presupuesto_mensual_detalle`
2. Actualizar formularios PHP
3. Extender JavaScript de validación

## Mantenimiento

### Backups
- Realizar backups regulares de la base de datos
- Exportar presupuestos importantes a Excel

### Actualizaciones
- Mantener actualizado el framework Bootstrap
- Revisar compatibilidad de PHP
- Actualizar librerías JavaScript

### Monitoreo
- Revisar logs de errores
- Monitorear rendimiento de consultas
- Validar integridad de datos

## Soporte

Para problemas o sugerencias:
1. Revisar la documentación técnica
2. Verificar logs del sistema
3. Contactar al administrador del sistema

---

**Versión:** 1.0.0  
**Última Actualización:** 2026  
**Compatible con:** PHP 8+, MySQL 5.7+, Bootstrap 5.3+
