# mi libreta — memoria del proyecto

App de **gastos personales** con login real, pensada para subir tal cual a un
hosting compartido tipo **Hostinger**. Sin build, sin dependencias de servidor:
se sube por FTP/administrador de archivos y funciona.

Versión actual: **v12** (ver `APP_VERSION` en `includes/functions.php`).

---

## 1. Arquitectura y stack

| Capa | Qué usa |
|------|---------|
| Backend | **PHP nativo 8.x**, sin frameworks. Endpoints planos en `/api/*.php`. |
| Base | **MySQL / MariaDB** (Hostinger corre MariaDB 10.x). Acceso vía PDO, prepared statements, `ERRMODE_EXCEPTION`, sin emular prepares. |
| Frontend | **HTML + CSS + JS vanilla**. SPA simple servida por `index.php`; todo el JS está en `assets/app.js` (un solo archivo, IIFE, sin módulos ni frameworks). Sin paso de build. |
| PWA | `manifest.json` + `service-worker.js`. Instalable en el celu ("agregar a pantalla de inicio"), abre a pantalla completa. El SW cachea lo estático. |
| Gráficos | **Chart.js** desde CDN (`cdn.jsdelivr.net`). Es la única dependencia externa del front. |
| Auth | Sesión PHP (cookie de 30 días) + tabla `auth_tokens` para "recordarme" real. CSRF por token en sesión, validado en cada endpoint que escribe. |
| Config secreta | `.env` **fuera de `public_html`** (un nivel arriba). Lo lee `includes/env.php`. `includes/config.php` nunca tiene credenciales. Variables de entorno reales del SO ganan sobre el `.env` (útil para testing). |

### Flujo de una request de API

1. El front hace `fetch('/api/x.php', { method:'POST', body: JSON, headers:{ 'X-CSRF-Token': CSRF } })`.
2. El endpoint hace `require_once includes/functions.php` → arrastra `auth.php` (arranca sesión, intenta "recordarme") → `db.php` → `config.php` → `env.php`.
3. `require_login_api()` corta con 401 si no hay sesión.
4. `require_csrf_api()` lee el body JSON **y** valida el token; corta con 403 si no.
5. El endpoint responde con `json_response($data, $code)` (siempre corta con `exit`).

### Archivos raíz (páginas, no API)

- `index.php` — app principal (protegida). Renderiza el shell HTML + carga `app.js`.
- `login.php`, `register.php`, `logout.php` — auth. reCAPTCHA opcional.
- `forgot-password.php`, `reset-password.php` — recuperación por mail (`mail()` nativo, `includes/mailer.php`).
- `.htaccess` — fuerza HTTPS, bloquea acceso web directo a `includes/` y `.env`.

### `includes/`

| Archivo | Rol |
|---------|-----|
| `config.php` | Define constantes `DB_*` y `RECAPTCHA_*` leyendo del `.env`. Muere con mensaje claro si falta el `.env`. |
| `env.php` | `load_env()` / `env()`. Busca `.env` un nivel arriba de `public_html` y, como red de seguridad, también en la raíz del proyecto. |
| `db.php` | `get_pdo()` — singleton PDO. |
| `auth.php` | sesión, `require_login()` / `require_login_api()`, `csrf_token()` / `csrf_valid()`. |
| `remember.php` | tokens de sesión persistente (selector + validator hasheado en `auth_tokens`). |
| `functions.php` | `APP_VERSION`, helpers de JSON/CSRF, helpers de meses, categorías por defecto, `PALETTE`, rate limiting de login, reCAPTCHA, `materialize_recurring_expenses()`. |
| `mailer.php` | envío del mail de recuperación. |

---

## 2. Tablas de la base

Esquema completo en `sql/schema.sql` (instalación nueva). Cada tanda de cambios
tiene además su `sql/upgrade_vN.sql` (ver §5).

| Tabla | Para qué sirve |
|-------|----------------|
| **users** | Cuentas. `email` único, `password_hash` (bcrypt), `reset_token` / `reset_expires` para recuperar contraseña. |
| **categories** | Categorías propias de cada usuario (`name`, `color` hex). Las usan gastos, presupuestos, cuotas, gastos fijos, vencimientos, plantillas **e ingresos**. |
| **expenses** | Movimientos de gasto. `amount`, `description`, `expense_date`, `month` (CHAR(7) `YYYY-MM`), `category_id` (FK SET NULL). Vínculos opcionales: `installment_id` + `installment_no` (cuota generada), `recurring_id` (gasto fijo generado). Índice `(user_id, month)`. |
| **budgets** | Presupuesto por `(user_id, category_id, month)`. PK compuesta. `amount`. |
| **month_settings** | **Legacy**: el ingreso único por mes de antes de la tabla `incomes`. Se sigue leyendo como fallback si falta `upgrade_v7`. `income_set.php` todavía escribe acá. |
| **templates** | "Gastos frecuentes": `name`, `amount`, `category_id` opcional. Cargás un gasto de un toque. |
| **installment_purchases** | Compras en cuotas: `description`, `total_amount`, `num_installments`, `first_month`, `category_id`. Cada una genera un `expenses` por mes. |
| **recurring_expenses** | Gastos fijos mensuales (Netflix, alquiler...): `name`, `amount`, `start_month`, `day_of_month`, `active`, `category_id`. Sin fecha de fin. |
| **due_dates** | Vencimientos (tarjeta, servicios, seguro): `name`, `amount` (nullable si varía), `due_day`, `recurring` (mensual) o `one_time_month`, `active`, `category_id`. |
| **due_date_payments** | Marca de "pagado" de un vencimiento en un mes. `UNIQUE (due_date_id, month)`. `expense_id` opcional (el gasto que se creó al marcar pagado). |
| **incomes** | Ingresos, varios por mes: `amount`, `description`, `income_date`, `month`, **`category_id`** (FK SET NULL, agregado en `upgrade_v8`). Índice `(user_id, month)`. |
| **auth_tokens** | "Mantener la sesión iniciada". `selector` (único), `validator_hash`, `expires_at`. Dura 30 días, se renueva sola. |
| **user_settings** | Preferencias: `hidden_sections` (JSON con las tarjetas ocultas), `notifications_enabled`. PK = `user_id`. |
| **login_attempts** | Intentos de login para el rate limiting. `email`, `ip`, `attempted_at`. Índices por email y por IP. |

Todas las FK de datos de usuario son `ON DELETE CASCADE` sobre `users`.
Las FK a `categories` son **`ON DELETE SET NULL`** (borrar una categoría no
borra sus gastos/ingresos, solo les saca la etiqueta) — salvo `budgets`, que es
`CASCADE` (un presupuesto sin categoría no tiene sentido).

---

## 3. Endpoints `/api`

Todos requieren sesión. Los que escriben requieren CSRF (body JSON + header
`X-CSRF-Token`). Responden JSON.

### Arranque y datos del mes
- **`bootstrap.php`** — carga inicial: categorías + plantillas + `settings` (paneles ocultos, notificaciones) + token CSRF + site key de reCAPTCHA. Degrada con gracia si falta `user_settings`.
- **`month.php`** (`GET ?month=YYYY-MM&q=&category_id=`) — todo lo del mes: `income` (total), `incomeEntries[]` (cada ingreso con `categoryId`/`categoryName`/`categoryColor`), `budgets{}`, `expenses[]` (con datos de cuota y de gasto fijo). **Endpoint crítico**: nunca debe romper; tiene fallbacks anidados si faltan `upgrade_v5/v7/v8`.

### Gastos
- **`expense_add.php`** — alta. Valida `categoryId` contra las del usuario.
- **`expense_update.php`** — edición parcial (solo los campos presentes en el body).
- **`expense_delete.php`** — baja. Devuelve el gasto borrado en `deleted` para el "deshacer".

### Ingresos
- **`income_add.php`** — alta. Acepta `categoryId` (opcional, nullable). Si falta `upgrade_v8`, guarda igual sin categoría.
- **`income_update.php`** — edición parcial. Acepta `categoryId` (incluido `null` para sacar la categoría). Si falta `upgrade_v8`, aplica el resto igual.
- **`income_delete.php`** — baja.
- **`income_set.php`** — **legacy**: escribe el ingreso único en `month_settings`. Ya no lo usa el front nuevo, se deja por compatibilidad.

### Presupuestos
- **`budget_set.php`** — upsert de presupuesto de una categoría en un mes.
- **`budgets_copy.php`** — copia los presupuestos de `fromMonth` a `month`.

### Categorías
- **`category_add.php`**, **`category_update.php`** (nombre + color), **`category_delete.php`** (los gastos/ingresos quedan sin categoría por la FK SET NULL).

### Plantillas / gastos frecuentes
- **`template_add.php`**, **`template_delete.php`**.

### Compras en cuotas
- **`installment_add.php`** — alta; genera un `expenses` por cada mes de la cuota.
- **`installments_list.php`** — lista con progreso (`paid_count` = cuántas cuotas ya llegaron).
- **`installment_cancel_remaining.php`** — borra **solo las cuotas futuras** (mes > actual); el historial ya cargado queda.
- **`installment_delete.php`** — borra la compra y **todas** sus cuotas (CASCADE).

### Gastos fijos recurrentes
- **`recurring_add.php`** — alta.
- **`recurring_list.php`** — lista (con datos de categoría). Error claro si falta `upgrade_v5`.
- **`recurring_toggle.php`** — pausar / reactivar (`active`).
- **`recurring_delete.php`** — baja; el historial generado queda (FK SET NULL).
- La materialización del gasto de cada mes la hace `materialize_recurring_expenses()` (en `functions.php`) cuando se pide ese mes en `month.php`.

### Vencimientos
- **`duedates_add.php`** — alta (mensual o de una vez).
- **`duedates_list.php`** — lista con estado de urgencia y si ya se pagó este ciclo. Error claro si falta `upgrade_v6`.
- **`duedates_mark_paid.php`** — marca pagado el ciclo; opcionalmente crea el `expenses` correspondiente y lo vincula.
- **`duedates_toggle.php`** — activar / desactivar.
- **`duedates_delete.php`** — baja (borra su historial de pagos en cascada; los gastos generados quedan).

### Resúmenes
- **`months_summary.php`** (`?count=6&month=`) — ingreso y gasto de los últimos N meses (gráfico comparativo). Fallback a `month_settings` si falta `incomes`.
- **`year_summary.php`** (`?year=`) — ingreso / gasto / balance del año, mes a mes y total por categoría.

### Import / export / backup
- **`export.php`** (`GET ?month=YYYY-MM` o `?month=all`) — descarga CSV de movimientos.
- **`import_bulk.php`** — importa filas de CSV (`rows[]`, máx 3000). Crea categorías que no existan.
- **`backup_export.php`** — descarga un JSON con **todo** (categorías, gastos, presupuestos, month_settings, ingresos con `category_id`, plantillas, cuotas, gastos fijos). Cada bloque usa `fetch_all_safe()`: si una tabla no está migrada, ese bloque sale vacío en vez de romper el backup.

### Settings
- **`settings_set.php`** — guarda `hiddenSections` (tarjetas ocultas) y `notificationsEnabled`.

---

## 4. Funcionalidades implementadas

- **Login / registro** con email + contraseña (bcrypt, CSRF). reCAPTCHA v2 **opcional** (si no hay claves en el `.env`, no aparece y todo funciona igual). **Rate limiting**: 8 intentos fallidos en 15 min bloquean por email y por IP (`login_attempts`).
- **Sesión persistente ("recordarme")**: token seguro en `auth_tokens`, 30 días, se renueva solo. Cerrar sesión lo invalida.
- **Recuperar contraseña**: link por mail válido 1 hora (`mail()` nativo).
- **Gastos** con categorías propias (nombre + color) y **presupuesto por categoría y por mes**, con barra de progreso y aviso al llegar al 90%. Copiar presupuestos del mes anterior.
- **Compras en cuotas**: se cargan una vez y generan el gasto de cada mes con etiqueta "cuota 3/12". "Cancelar restantes" borra solo las futuras sin perder el historial; borrar la compra borra todo.
- **Gastos fijos recurrentes**: se cargan una vez y se generan solos cada mes hasta pausarlos o borrarlos.
- **Vencimientos** con color según urgencia (vencido / vence pronto / falta) y botón "marcar pagado" que puede crear y vincular el gasto.
- **Ingresos múltiples por mes** (sueldo, freelance, ventas...), cada uno editable/borrable, **con categoría opcional** (mismo sistema de categorías y colores que los gastos). El total del mes es la suma.
- **Importar / exportar CSV** de movimientos. **Backup completo en JSON**.
- **Resumen anual**: ingreso / gasto / balance del año, gráfico mes a mes, total por categoría.
- **Panel de configuración** (ícono de tuerca): import/export, backup, y elegir qué tarjetas mostrar (paneles ocultables, se guarda en `user_settings`).
- **Secciones plegables** en el dashboard: tocar el título de una tarjeta la colapsa; se recuerda el estado.
- **PWA instalable**, formato de moneda argentino (`$ 130.000`), etiquetas "Hoy"/"Ayer", "deshacer" al borrar un gasto, todo se guarda solo.

---

## 5. CONVENCIÓN DE VERSIONADO ⚠️ (leer antes de tocar `app.js` o `style.css`)

Hay una constante **`APP_VERSION`** en `includes/functions.php`. Se usa como
`?v=X` en las URLs de `assets/app.js` y `assets/style.css` en `index.php`.
En `service-worker.js` hay un **`CACHE_NAME`** y una lista `PRECACHE` con esas
mismas URLs versionadas.

**Cada vez que se toca `assets/app.js` o `assets/style.css` hay que subir ese
número en los TRES lugares:**

1. `APP_VERSION` en `includes/functions.php` (ej. `'v12'` → `'v13'`).
2. `CACHE_NAME` en `service-worker.js` (`"mi-libreta-v12"` → `"mi-libreta-v13"`).
3. Los `?v=v12` dentro de `PRECACHE` en `service-worker.js`.

Si no se sube, **el service worker sigue sirviendo la versión vieja cacheada**
a los usuarios que ya abrieron la app. Esto ya pasó (fue el bug de las cuotas)
y costó varias vueltas detectarlo. El SW hoy usa estrategia "red primero" para
estáticos justamente para mitigarlo, pero **igual hay que subir la versión**.

---

## 6. PATRÓN DE MIGRACIONES

- Cada tanda de cambios de base tiene un **`sql/upgrade_vN.sql` idempotente**
  (seguro de correr más de una vez). Se pega entero en phpMyAdmin → pestaña SQL.
- El mismo cambio se refleja en **`sql/schema.sql`** para instalaciones nuevas.
- Idempotencia:
  - Tablas: `CREATE TABLE IF NOT EXISTS`.
  - Columnas: `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (soportado en MariaDB, que es lo que corre Hostinger).
  - Foreign keys: chequear `information_schema.TABLE_CONSTRAINTS` y agregarla con `PREPARE`/`EXECUTE` solo si no existe (así no tira "duplicate" al re-correr). Ver `upgrade_v8.sql` como referencia.
- **Los endpoints críticos para que la app cargue degradan con gracia** si falta
  una migración: **nunca deben romper toda la app**, como mucho esa tarjeta o esa
  función puntual. Endpoints con fallback: `bootstrap.php` (`user_settings`),
  `month.php` (`recurring`, `incomes`, `incomes.category_id`),
  `months_summary.php` / `year_summary.php` (`incomes` → `month_settings`),
  `backup_export.php` (cada bloque por separado), `income_add.php` /
  `income_update.php` (`incomes.category_id`). Endpoints de tarjetas secundarias
  (`recurring_list`, `duedates_list`, `installments_list`) sí devuelven un error
  claro "corré `sql/upgrade_vN.sql`" en vez de fallback.

### Historial de upgrades

| Archivo | Trae |
|---------|------|
| `upgrade_v2.sql` | columnas de recuperar contraseña en `users` |
| `upgrade_v3.sql` | tabla `auth_tokens` (sesión persistente) |
| `upgrade_v4.sql` | `installment_purchases` + `expenses.installment_id/installment_no` |
| `upgrade_v5.sql` | `user_settings`, `recurring_expenses`, `expenses.recurring_id`, `login_attempts` |
| `upgrade_v6.sql` | `due_dates` + `due_date_payments` |
| `upgrade_v7.sql` | tabla `incomes` + migra el ingreso único de `month_settings` a una entrada "Ingreso del mes" |
| `upgrade_v8.sql` | **`incomes.category_id`** (FK `categories` `ON DELETE SET NULL`) — ingresos por categoría |

---

## 7. LECCIÓN DE HTML ⚠️: nunca un `<button>` dentro de otro `<button>`

Anidar un `<button>` dentro de otro `<button>` es HTML inválido. El navegador
lo "corrige" cerrando el botón externo antes de tiempo y **rompe el layout
entero** de esa zona.

Por eso, los **headers clickeables de tarjetas** (los que colapsan la sección y
que además tienen botones de acción adentro — editar, agregar, etc.) usan
**`<div role="button" tabindex="0">`** en vez de `<button>`. Si vas a hacer un
contenedor clickeable que puede tener botones adentro, usá el `div` con `role` y
manejá `click` + `keydown` (Enter/Espacio) a mano.

---

## 8. Testing local

No hay servicio de MySQL permanente en la máquina de desarrollo; se usa una
instancia aislada de MariaDB (la de XAMPP) en un datadir temporal y puerto
alternativo, con bases `gastos_new` (schema nuevo) y `gastos_old` (schema previo,
sin la última migración). Se levanta `php -S` con un router que pre-autentica y
se prueban los endpoints con `curl`. Antes de dar por terminado un cambio de
base: probar **instalación nueva** (schema.sql) **y base vieja** (sin el
`upgrade_vN.sql`, para confirmar que degrada sin romper), y correr el
`upgrade_vN.sql` **dos o tres veces seguidas** para confirmar idempotencia.
