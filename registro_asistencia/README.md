# Módulo de Registro de Asistencia

## Descripción
Sistema completo de registro de asistencia para colaboradores con funcionalidad de edición y flujo de aprobación.

## Características

### Para Colaboradores:
- **Registro de Entrada/Salida**: Botones simples para marcar el inicio y fin de la jornada laboral
- **Ubicación**: Registro automático de IP (preparado para futura implementación de GPS)
- **Visualización**: Tabla con DataTables mostrando todos los registros del mes actual
- **Edición**: Posibilidad de editar registros en caso de olvido
- **Resumen Mensual**: Panel con total de horas trabajadas, discriminando aprobadas y pendientes

### Para Administradores:
- **Aprobaciones**: Panel para revisar y aprobar/rechazar registros editados
- **Auditoría**: Registro completo de todos los cambios realizados
- **Estadísticas**: Vista general del estado de los registros mensuales

## Instalación

### 1. Base de Datos
Ejecuta el script SQL para crear las tablas necesarias:
```sql
-- Ejecutar el archivo asistencia_database.sql en tu base de datos
mysql -u root -p desembolsos_db < asistencia_database.sql
```

### 2. Estructura de Archivos
Los archivos se crean automáticamente en:
```
c:\xampp\htdocs\desembolsos\registro_asistencia\
├── index.php                 # Página principal para colaboradores
├── api_registrar.php         # API para registrar entrada/salida
├── editar_registro.php       # Formulario de edición de registros
├── admin_aprobaciones.php    # Panel de aprobaciones para admin
└── README.md                 # Este archivo
```

### 3. Permisos
Asegúrate de que los usuarios con rol 'Colaborador' tengan acceso al módulo en el sistema de permisos.

## Uso

### Para Colaboradores:
1. Accede al sistema y ve al menú principal
2. Haz clic en "Registro de Asistencia"
3. Usa los botones "Registrar Entrada" y "Registrar Salida"
4. Revisa tu resumen mensual en el panel inferior
5. Si olvidaste marcar, edita el registro desde la tabla

### Para Administradores:
1. Accede al sistema como administrador
2. En el menú principal aparecerá "Aprobaciones de Asistencia"
3. Revisa los registros pendientes y aprueba/rechaza según corresponda

## Flujo de Aprobación

1. **Registro Normal**: Cuando un colaborador marca entrada/salida, el registro queda en estado 'aprobado'
2. **Edición**: Si un colaborador edita un registro, este cambia a 'pendiente_aprobacion'
3. **No se computa**: Mientras esté pendiente, no se incluye en los resúmenes de horas
4. **Aprobación**: El administrador puede aprobar o rechazar el registro editado
5. **Auditoría**: Todos los cambios quedan registrados en la tabla de auditoría

## Campos de Base de Datos

### Tabla `asistencia`:
- `id`: Identificador único
- `usuario_id`: ID del colaborador
- `fecha`: Fecha del registro
- `hora_entrada`: Hora de entrada
- `hora_salida`: Hora de salida
- `ubicacion_entrada/salida`: IP actual (preparado para GPS)
- `estado`: 'aprobado', 'pendiente_aprobacion', 'rechazado'
- `observaciones`: Notas del colaborador
- `editado_por`: ID del admin que editó
- `fecha_edicion`: Fecha de la última edición

### Tabla `asistencia_auditoria`:
- Registra todos los cambios con detalles de quién, cuándo y qué cambió

## Personalización

### Estilos
El sistema usa Bootstrap 5 con gradientes personalizados. Puedes modificar los colores en las variables CSS:
```css
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}
```

### Configuración
- **Timezone**: El sistema usa el timezone configurado en PHP
- **Horas Laborales**: No hay límite configurado, puedes agregar validaciones si es necesario
- **GPS**: Los campos de ubicación están listos para implementar geolocalización

## Seguridad
- Uso de tokens CSRF en todas las operaciones
- Validación de sesión y permisos
- Registro de auditoría completo
- Sanitización de entradas

## Soporte
Para cualquier problema o consulta, revisa los logs de errores de PHP y la tabla de auditoría para rastrear problemas.
