# mi libreta — PHP + MySQL para Hostinger (v2)

App de gastos personales con login real (usuario y contraseña), hecha en **PHP nativo**
(sin frameworks) y **MySQL**, lista para subir a Hostinger tal cual.

Probada de punta a punta con PHP 8.3 y MariaDB antes de entregarla: registro, login,
carga/edición/borrado de gastos, presupuestos, categorías, plantillas, exportar CSV,
recuperar contraseña, copiar presupuestos y resumen anual — todo probado con curl
contra una base real.

## ⚠️ Seguridad: dónde va la contraseña de la base

`includes/config.php` **ya no contiene ninguna contraseña real** — lee todo
desde un archivo `.env` que subís aparte, un nivel arriba de `public_html`
(fuera del webroot, donde el navegador no puede tocarlo). Ver la sección
**"2. Completar la conexión a la base"** más abajo para el paso a paso.

Si venías de una versión anterior donde `includes/config.php` sí tenía tu
contraseña real adentro: consideralá comprometida y **cambiala ahora**
desde hPanel → Bases de datos → MySQL → Cambiar contraseña, aunque no
sospeches que se filtró — es gratis y toma un minuto.

## ⚠️ Seguridad: las contraseñas de tus usuarios viajan sin cifrar si no usás HTTPS

El `.htaccess` ahora **fuerza HTTPS** (redirige automáticamente cualquier
visita por `http://` a `https://`). Esto es necesario porque, sin HTTPS,
el email y la contraseña del login/registro viajan **en texto plano** por
la red — cualquiera en la misma wifi podría verlos. Así es como
normalmente se detectan "contraseñas expuestas": no es un problema de
cómo se guardan (eso ya está bien, con `password_hash`), sino de cómo
viajan mientras las escribís.

**Antes de subir esta versión, activá el certificado SSL gratis de
Hostinger** (si todavía no lo hiciste): hPanel → tu sitio → **Seguridad
→ SSL** → activarlo para tu dominio (puede tardar unos minutos en
propagarse). Si subís el `.htaccess` nuevo sin tener el SSL activo
todavía, el sitio va a quedar inaccesible (te redirige a un `https://`
que todavía no existe) hasta que actives el certificado.

## Si ya tenías la v1 instalada (actualizar sin perder datos)

1. En phpMyAdmin de tu base, pestaña **SQL**, pegá y ejecutá el contenido de
   `sql/upgrade_v2.sql` (agrega las columnas para "recuperar contraseña"; tus
   gastos, categorías y presupuestos existentes no se tocan).
2. Subí todos los archivos de esta carpeta a `public_html`, **reemplazando** los
   anteriores (incluye archivos nuevos: `manifest.json`, `service-worker.js`,
   `forgot-password.php`, `reset-password.php`, `assets/icons/`, etc.).
3. `includes/config.php` ya no lleva datos reales (ahora se leen de un
   `.env` fuera de `public_html`, ver sección 2 más abajo) — podés
   reemplazarlo sin problema.
4. Podés borrar `default.php` (es la página de bienvenida que pone Hostinger
   por defecto; tu app no la usa).

## Si ya tenías la v2 instalada (agregar sesión persistente)

1. En phpMyAdmin, pestaña **SQL**, pegá y ejecutá `sql/upgrade_v3.sql` (crea
   una tabla nueva para los tokens de sesión; no toca tus datos).
2. Subí de nuevo `login.php`, `register.php`, `logout.php`, `includes/auth.php`,
   `includes/remember.php` (nuevo) y `assets/style.css`.

## Si ya tenías la v3 instalada (agregar compras en cuotas + íconos)

1. En phpMyAdmin, pestaña **SQL**, pegá y ejecutá `sql/upgrade_v4.sql` (crea
   la tabla de compras en cuotas y agrega 2 columnas a `expenses`; tus gastos,
   categorías y presupuestos existentes no se tocan ni se borran).
2. Subí de nuevo todos los archivos — hay archivos nuevos (`api/installment_add.php`,
   `api/installments_list.php`, `api/installment_delete.php`, `favicon.ico`,
   los íconos nuevos en `assets/icons/`) y varios modificados
   (`index.php`, `assets/app.js`, `assets/style.css`, `api/month.php`, y las
   4 páginas de login/registro/recuperar clave, solo para el favicon).
3. `includes/config.php` no lleva datos reales, se puede reemplazar sin problema.

## Si ya tenías la v4 instalada (agregar esta tanda grande)

1. En phpMyAdmin, pestaña **SQL**, pegá y ejecutá `sql/upgrade_v5.sql` (crea
   3 tablas nuevas y una columna en `expenses`; no toca tus datos existentes).
2. Subí todos los archivos — hay muchos nuevos y modificados esta vez
   (ver lista completa en "Estructura" más abajo).
3. (Opcional) Si querés el captcha, sacá tus claves en
   [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin)
   (elegí **reCAPTCHA v2 → casilla "No soy un robot"**) y agregá
   `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` a tu `.env` (fuera de
   `public_html`, ver sección 2 más abajo).
   Si los dejás vacíos, el captcha no aparece pero todo el resto funciona igual.
4. `includes/config.php` no lleva datos reales, se puede reemplazar sin problema.

## Si ya tenías la v5 instalada (agregar vencimientos)

1. En phpMyAdmin, pestaña **SQL**, pegá y ejecutá `sql/upgrade_v6.sql`
   (crea 2 tablas nuevas; no toca tus datos existentes).
2. Subí todos los archivos — hay archivos nuevos (`api/duedates_*.php`) y
   varios modificados (`index.php`, `assets/app.js`, `assets/style.css`, y
   tres endpoints — `bootstrap.php`, `month.php`, `recurring_list.php` —
   que corregí para que sigan funcionando aunque falte alguna migración
   vieja; ver más abajo).
3. `includes/config.php` no lleva datos reales, se puede reemplazar sin problema.

## Si ya tenías la v6 instalada (agregar ingresos múltiples)

1. En phpMyAdmin, pestaña **SQL**, pegá y ejecutá `sql/upgrade_v7.sql`.
   Esto crea la tabla nueva **y además migra automáticamente** lo que ya
   tenías cargado como un solo ingreso por mes: cada mes con un valor
   pasa a ser una entrada llamada "Ingreso del mes" en la lista nueva.
   No se pierde nada, y es seguro correrlo más de una vez.
2. Subí todos los archivos — hay 3 endpoints nuevos (`income_add.php`,
   `income_update.php`, `income_delete.php`) y varios modificados
   (`assets/app.js`, `api/month.php`, `api/months_summary.php`,
   `api/year_summary.php`, `api/backup_export.php`).
3. `includes/config.php` no lleva datos reales, se puede reemplazar sin problema.

## Novedades de esta versión

- **Ingresos múltiples por mes**: en vez de un solo número fijo, ahora
  tocás "Ingreso" en el ticket de arriba y se abre la lista de todo lo que
  fue entrando ese mes — podés ir agregando a medida que te pagan (sueldo,
  un freelance, una venta, lo que sea), y editar o borrar cada entrada por
  separado. El total del ticket es la suma de todos.

- **Vencimientos**: tarjeta nueva para no olvidarte de pagar cosas (tarjeta
  de crédito, alquiler, seguro, servicios). Cargás el nombre, el día del
  mes en que vence, y opcionalmente el monto (si varía cada vez, como la
  luz o el gas, lo dejás en blanco). Puede ser mensual (se repite solo) o
  de una sola vez (para algo puntual, como la patente). Se ve con un
  color según la urgencia: vencido, vence pronto, o falta bastante.
  El botón "Marcar pagado" carga el gasto correspondiente de una y lo
  vincula, para no tener que cargarlo dos veces a mano.

**Arreglo importante de esta versión**: encontré y corregí un problema en
`bootstrap.php`, `month.php` y `recurring_list.php` donde, si a alguien le
faltaba correr alguna migración anterior, la app entera podía dejar de
cargar en vez de solo avisar en la tarjeta correspondiente. Ya está
resuelto y probado con una base de datos de 3 versiones atrás.

- **Panel de Configuración** (ícono de tuerca en el header): desde ahí se
  importa/exporta CSV, se hace el backup completo, y se elige qué tarjetas
  ver en la pantalla principal — cada tarjeta se puede ocultar si no la usás.
- **Gastos fijos mensuales**: cargás una vez (Netflix, gimnasio, alquiler...)
  y se genera solo cada mes, sin fecha de fin, hasta que lo pausés o borres.
  Tarjeta nueva "Gastos fijos".
- **Cancelar cuotas restantes**: en "Compras en cuotas" ahora hay dos
  acciones — el ícono de lápiz cancela solo las cuotas que todavía no
  llegaron (las que ya se cargaron quedan en tu historial), y el tacho
  sigue borrando todo como antes.
- **Total comprometido**: la tarjeta de cuotas ahora muestra cuánto te
  falta pagar en total, sumando todas tus compras activas.
- **Backup completo**: desde Configuración, un botón descarga todos tus
  datos (gastos, categorías, presupuestos, cuotas, gastos fijos) en un
  archivo JSON — un respaldo aparte del CSV de movimientos.
- **Límite de intentos de login**: 8 intentos fallidos en 15 minutos y se
  bloquea (por email y por IP), protección básica contra fuerza bruta.
- **Google reCAPTCHA** (opcional): protege el registro y el login de bots.
  Necesita que saques tus propias claves gratis (ver arriba); si no las
  configurás, la app funciona exactamente igual sin el captcha.
- **Confirmación al usar un "frecuente"**: ahora antes de cargar el gasto
  te pregunta "¿Querés agregar un movimiento de X por $Y?" — evita que
  un toque de más cargue algo sin querer.
- **Notificaciones cuando te acercás al límite**: opcional, se activa desde
  Configuración. Importante: son notificaciones del navegador mientras
  tenés la app *abierta* — no es un push real que llegue con la app cerrada
  (eso necesitaría más infraestructura del lado del servidor).

- **Secciones plegables**: tocá el título de cualquier tarjeta (Presupuestos,
  Movimientos, Compras en cuotas, etc.) para colapsarla o volver a abrirla.
  Se acuerda de cómo la dejaste la próxima vez que entrés.
- **Importar movimientos desde CSV**: en la tarjeta "Movimientos" hay un botón
  "importar". Subís un archivo CSV con columnas `Fecha;Categoría;Descripción;Monto`
  (fecha en formato AAAA-MM-DD) y carga todo de una — si una categoría no
  existe todavía, la crea sola. Junto con este zip te dejo `importar-gastos.csv`,
  ya armado a partir de tu planilla vieja (518 movimientos desde marzo 2025),
  para que lo subas directo.

- **Compras en cuotas**: cargás la compra una sola vez (qué compraste, monto
  total, cantidad de cuotas, categoría y en qué mes arranca) y la app genera
  sola el gasto correspondiente en cada uno de esos meses, con una etiqueta
  "cuota 3/12" para identificarlas. Cuentan para el presupuesto de su
  categoría como cualquier otro gasto. Hay una tarjeta nueva ("Compras en
  cuotas") que muestra el progreso de cada una; si borrás una compra, se
  borran todas sus cuotas (pasadas y futuras) — la app te avisa antes.
- **Íconos nuevos**: pantalla de carga, ícono de la app instalable y favicon
  (el ícono de la pestaña del navegador), todos con onda a la billetera que
  mandaste de referencia.
- Confirmado: todos los montos que se muestran en la app usan formato
  argentino (`$ 130.000`, con puntos de miles).

- **Sesión persistente ("mantener la sesión iniciada")**: con el casillero
  tildado (por defecto) en el login, no te vuelve a pedir usuario y contraseña
  aunque cierres el navegador o el celular esté días sin abrir la app — dura
  30 días y se renueva sola cada vez que entrás. Usa un token seguro guardado
  en la base (no solo una cookie de sesión común), así que sobrevive aunque
  el servidor limpie la sesión vieja. Si alguna vez alguien te agarra el
  celular, tenés el botón de cerrar sesión (ícono de salida en el header) que
  invalida ese token al toque.

- **Instalable como app (PWA)**: desde el navegador del celular, "Agregar a
  pantalla de inicio" — abre a pantalla completa con ícono propio, como una
  app nativa. Funciona con Chrome/Safari en Android e iOS. *Necesita que el
  sitio tenga HTTPS* (Hostinger da SSL gratis desde hPanel → Seguridad → SSL;
  activalo si todavía entrás por `http://`).
- **Recuperar contraseña**: link de "¿Olvidaste tu contraseña?" en el login,
  manda un mail con un link válido por 1 hora para elegir una nueva. Usa la
  función `mail()` nativa de PHP (funciona en Hostinger sin configuración
  extra; si tu plan no envía mails, avisame y lo cambio a SMTP).
- **Copiar presupuestos del mes anterior**: si entrás a un mes sin presupuestos
  cargados y el mes anterior sí tenía, aparece un aviso para copiarlos con un
  toque en vez de cargarlos de cero.
- **Aviso de límite cercano**: banner cuando una categoría llega al 90% de su
  presupuesto, para que lo veas antes de pasarte.
- **Resumen anual**: botón en el header (ícono de calendario) con el ingreso,
  gasto y balance del año, gráfico mes a mes, y el total por categoría.

## 1. Crear la base de datos en Hostinger (5 min)

1. Entrá a **hPanel** → tu sitio → **Bases de datos → Bases de datos MySQL**.
2. Creá una base nueva. Hostinger te va a dar 3 datos, anotalos:
   - Nombre de la base (algo como `u123456789_libreta`)
   - Usuario (algo como `u123456789_usuario`)
   - Contraseña (la que vos elijas)
   - El host casi siempre es `localhost`.
3. En esa misma pantalla, tocá **Administrar → phpMyAdmin** para esa base.
4. Andá a la pestaña **SQL**, pegá **todo** el contenido del archivo `sql/schema.sql`
   de esta carpeta, y tocá **Continuar/Ejecutar**. Esto crea las 6 tablas que
   necesita la app (usuarios, categorías, gastos, presupuestos, ingreso y plantillas).

## 2. Completar la conexión a la base (con un `.env`, fuera de `public_html`)

**Importante — por seguridad, la contraseña real de la base NO va dentro de
`public_html`.** Si la pusieras ahí (como en `includes/config.php`), viajaría
cada vez que compartís o subís esta carpeta (zip, git, email), y cualquiera
con ese archivo tendría acceso completo a tu base de datos.

En cambio, los datos van en un archivo `.env` que subís **un nivel arriba**
de `public_html` (en la raíz de tu cuenta de Hostinger), fuera del alcance
del navegador:

1. En hPanel, andá a **Archivos → Administrador de archivos**.
2. Subí un nivel: de `public_html` a la carpeta de arriba (tu home).
3. Creá ahí un archivo nuevo llamado exactamente `.env` (al lado de la
   carpeta `public_html`, **no adentro**).
4. Pegale este contenido, completando con los 4 datos del paso 1:

```env
DB_HOST=localhost
DB_NAME=u123456789_libreta
DB_USER=u123456789_usuario
DB_PASS=tu_contraseña_de_la_base
DB_CHARSET=utf8mb4
```

`includes/config.php` ya viene preparado para leer estos valores solo —
no hace falta tocarlo. Si el `.env` falta o está mal ubicado, la app te va
a avisar con un mensaje claro en vez de romperse en silencio.

(`.env.example`, que sí va dentro de esta carpeta, es solo una plantilla sin
datos reales — sirve de referencia, no lo confundas con el `.env` real.)

## 3. Subir los archivos a Hostinger

1. En hPanel andá a **Archivos → Administrador de archivos** (o usá FTP si preferís).
2. Entrá a la carpeta `public_html` de tu dominio (o la subcarpeta si vas a usar un
   subdominio, ej. `public_html/gastos`).
3. Subí **todo el contenido** de esta carpeta ahí adentro (no la carpeta en sí, sino
   lo que tiene adentro: `index.php`, `login.php`, `api/`, `assets/`, `includes/`, etc.)
   — el `.env` real **no** va acá, va un nivel arriba (paso 2).
4. Listo. Entrá a tu dominio (ej. `https://tudominio.com/` o `https://tudominio.com/gastos/`),
   te va a aparecer la pantalla de registro.

**Importante:** la carpeta `includes/` tiene el archivo con la contraseña de tu base
de datos. El `.htaccess` incluido ya bloquea el acceso directo a esa carpeta por
navegador, pero confirmá que tu hosting tenga Apache con `mod_rewrite` activo
(en Hostinger viene activo por defecto).

## 4. Ya está

Entrá a tu dominio, tocá "Creá una" para registrar tu primer usuario, y arrancá a
cargar gastos. Cada persona que se registre después va a tener sus propios datos,
separados — nadie ve los gastos de otro usuario.

## Qué incluye

- Registro / login con email y contraseña (hash bcrypt, protección CSRF).
- Cada usuario ve solo sus propios datos.
- Categorías propias, editables y con opción de borrar (sus gastos pasan a "Otros").
- Presupuesto por categoría y por mes, con barra de progreso.
- **Gastos frecuentes**: guardá "turno padel $9.000" una vez y después lo cargás
  de un toque.
- Gráfico de torta de gastos por categoría del mes.
- **Gráfico comparativo** de los últimos 6 meses.
- **Buscador y filtro** de movimientos por texto o categoría.
- **Exportar a CSV** (del mes actual o de todo el historial), para abrir en Excel.
- Deshacer al borrar un gasto (aparece un aviso con la opción por unos segundos).
- Fechas con etiqueta "Hoy" / "Ayer".
- Todo se guarda solo, sin botón de guardar.

## Estructura

```
index.php            página principal (protegida, requiere login)
login.php             iniciar sesión
register.php          crear cuenta
logout.php             cerrar sesión
forgot-password.php    pedir link de recuperación
reset-password.php     elegir nueva contraseña desde el link del mail
manifest.json          metadata de la app instalable (PWA)
service-worker.js       cachea lo estático para que abra rápido
favicon.ico             ícono de la pestaña del navegador
includes/
  config.php            lee la conexión a MySQL y las claves de reCAPTCHA desde el .env
  env.php                cargador del archivo .env (fuera de public_html)
  db.php                conexión PDO
  auth.php              sesión, login requerido, tokens CSRF
  remember.php           sesión persistente ("mantener la sesión iniciada")
  functions.php          helpers varios, rate limiting, reCAPTCHA, gastos fijos
  mailer.php             envío del mail de recuperación
api/                    endpoints JSON que usa el frontend (todos requieren login)
  bootstrap.php           categorías + plantillas + settings + token CSRF inicial
  month.php               datos de un mes (ingreso, presupuestos, gastos)
  expense_add.php, expense_update.php, expense_delete.php
  income_add.php, income_update.php, income_delete.php
  budget_set.php, budgets_copy.php
  category_add.php, category_update.php, category_delete.php
  template_add.php, template_delete.php
  installment_add.php, installments_list.php, installment_delete.php,
  installment_cancel_remaining.php
  recurring_add.php, recurring_list.php, recurring_toggle.php, recurring_delete.php
  duedates_add.php, duedates_list.php, duedates_mark_paid.php,
  duedates_toggle.php, duedates_delete.php
  settings_set.php        guardar paneles visibles / notificaciones
  months_summary.php      últimos N meses (para el gráfico comparativo)
  year_summary.php        resumen anual por mes y por categoría
  export.php               exportar CSV
  import_bulk.php          importar movimientos desde CSV
  backup_export.php        backup completo en JSON
assets/
  style.css               todo el diseño
  app.js                  toda la lógica del frontend (JS nativo, sin frameworks)
  icons/                  íconos de la app instalable y el favicon
sql/
  schema.sql               pegar en phpMyAdmin para crear las tablas (instalación nueva)
  upgrade_v2.sql            pegar si venías de la v1 (recuperar contraseña)
  upgrade_v3.sql            pegar si venías de la v2 (sesión persistente)
  upgrade_v4.sql            pegar si venías de la v3 (compras en cuotas)
  upgrade_v5.sql            pegar si venías de la v4 (configuración, gastos fijos, etc.)
  upgrade_v6.sql            pegar si venías de la v5 (vencimientos)
  upgrade_v7.sql            pegar si venías de la v6 (ingresos múltiples)
```

## Notas técnicas

- El gráfico usa **Chart.js** cargado desde un CDN público (`cdn.jsdelivr.net`) —
  no necesita instalación, pero si tu red bloquea CDNs externos avisame y lo cambio
  por una copia local del archivo.
- No hay build ni `npm install`: es PHP y JS planos, se sube y funciona.
- Si en algún momento cambiás de hosting o querés correrlo en tu compu para probar,
  necesitás PHP 8+ con la extensión `pdo_mysql` y un servidor MySQL/MariaDB corriendo;
  después alcanza con `php -S localhost:8000` parado en esta carpeta.
