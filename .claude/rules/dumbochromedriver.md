# DumboChromeDriver — Contexto para otros proyectos
   del ecosistema (Uroboros y futuros)

## Qué es
WebDriver PHP puro para pruebas E2E de aplicaciones
DumboPHP/DumboJS — sin Selenium, sin dependencias de
Node. Habla directo el protocolo DevTools de Chrome
(WebSocket + JSON-RPC), lanzado headless. Construido
íntegramente durante el desarrollo de Komodo, ya
usado en producción de pruebas ahí.

Repo: ~/web/DumboChromeDriver
Instala en: /etc/dumboChromeDriver/ (via
sudo php install.php desde el repo — nunca editar
/etc/ directamente)

## Por qué existe
Verificar manualmente cada flujo de UI, repetidamente,
no escala. DumboChromeDriver permite escribir scripts
PHP que abren un navegador real, navegan, interactúan
con formularios/paneles/componentes DumboJS, y
verifican resultados — incluyendo casos cross-rol
(ej: un flujo que cruza contador → guarda → admin
verificando el mismo dato reflejado consistentemente
en las tres pantallas).

## Capacidades actuales (v1-v3 + fixes)

```php
$client = new DevToolsClient();
$client->start('https://tu-app.local/login');

// Navegación e interacción básica
$client->navigate($url);
$client->fill('selector', 'valor');
$client->click('selector');

// Espera correcta (agnóstica a cualquier framework)
$client->waitUntilInteractable('selector');
// → espera hasta que el elemento exista, sea
//   visible, tenga dimensiones reales, y no esté
//   deshabilitado. NO depende de atributos custom
//   de ningún framework — funciona igual con
//   DumboJS, React, Vue, o HTML plano.

// Reintento de acciones con condición de éxito
$client->retryUntil(
    fn() => $client->click('button.submit'),
    fn() => !str_contains($client->evaluate('location.href'), '/login'),
    15000
);
// → cubre el caso donde un elemento se ve listo
//   pero su lógica de negocio (ej: listener de
//   submit conectado por un módulo JS separado)
//   aún no terminó de conectarse. CUIDADO: no usar
//   ciegamente en formularios no idempotentes
//   (riesgo de doble-submit si el primer intento
//   sí tuvo efecto).

// Composición de elementos (v3) — sin JS inline
$el = $client->find('selector');
$el->closest('.ancestro')->count('tbody tr');
$client->findByText('h3', 'texto visible');

// Aislamiento — perfil temporal por corrida
// (automático desde el fix de esta sesión — cada
// start() crea un directorio de perfil único,
// stop() lo limpia, sin Service Worker/cookies/
// cache contaminando entre corridas)
```

## Aprendizajes duros — no los repitas

### 1. El.click() sintético vs. la API real de un
   componente puede estar rota — y solo se descubre
   probando de verdad

Encontramos un bug real (no solo de testing): un
componente DumboJS (dmb-button) definía un método
de INSTANCIA llamado click(method) que SOMBREABA
HTMLElement.prototype.click() nativo. Cualquier
código que llamara elemento.click() (sin argumento
— el uso estándar del DOM) invocaba el método
propio en vez del nativo, y no hacía NADA — sin
error, sin evento, sin rastro. Esto afectaba
potencialmente a usuarios reales, no solo al test.

Lección: si un componente custom define un método
con el mismo nombre que una API nativa del DOM
(click, focus, blur, submit), verifica que no la
esté sombreando accidentalmente. El fix es renombrar
el método propio (ej: onClick en vez de click),
nunca asumir que "funciona en el navegador real"
demuestra que el mecanismo es correcto — un click de
mouse real dispara el evento DOM directamente, sin
pasar por el método de la clase.

### 2. Componentes anidados con renderizado async —
   la carrera es real y silenciosa

Un componente padre que hace querySelector() sobre
un hijo, y luego intenta usar el contenido interno
de ese hijo, puede fallar con "Cannot read properties
of null" si el hijo usa templateUrl (carga async) —
el padre puede ejecutar su lógica ANTES de que el
hijo termine su propio connectedCallback().

Solución: cualquier método init() de un componente
padre que dependa de un hijo debe verificar
hijo.hasAttribute('rendered') primero — si ya está,
proceder directo; si no, escuchar UNA VEZ el evento
'dmb.after-rendered' del hijo antes de proceder.
NUNCA asumir que querySelector() + acceso inmediato
es seguro quando hay componentes anidados.

### 3. dmb-panel + source ya fijo en el HTML = doble
   fetch — encontrado 3 VECES en la misma sesión

Si un panel ya trae source="/ruta" fijo en el HTML
servido por el servidor, el navegador dispara su
propio fetch automático al conectar el custom
element — y si el código (de test o de la propia
app) también llama panel.open() o reasigna el mismo
source, se dispara un SEGUNDO fetch concurrente. El
que resuelve último pisa cualquier interacción hecha
con el primero — datos que "se vacían misteriosamente"
sin ningún error visible.

Patrón de defensa: antes de abrir un panel
programáticamente, verificar si ya trae su source
cargado de fábrica — si es así, confiar en el fetch
automático en vez de repetirlo.

Este patrón se repitió 3 veces en Komodo — vale la
pena, en cualquier proyecto nuevo con este mismo
sistema de componentes, revisar dmb-panel.directive.js
y considerar un guard interno (no volver a fetch si
el source no cambió) en vez de que cada consumidor
tenga que evitarlo manualmente.

### 4. Verificar el esqueleto puro, no solo el
   resultado final con overrides/fixtures aplicados

Cuando se construyó un generador de contrato
(análogo: OpenAPI generator), un bug de detección
quedó enmascarado durante una ronda completa de
verificación porque el archivo de overrides ya traía
los valores completos manualmente — el resultado
final se veía bien aunque el generador mismo
funcionara mal. Solo se descubrió invocando la
función de detección directamente, sin overrides.

Lección aplicable a cualquier test: si hay una capa
de datos/configuración manual que puede enmascarar
un bug del mecanismo automático debajo, verifica el
mecanismo automático de forma aislada, no solo el
resultado compuesto final.

### 5. TLS/certificados locales — sospecha legítima,
   nunca confirmada del todo

Hubo una ronda de investigación de intermitencia de
login en Chrome headless (contra un dominio .local
con certificado autofirmado) que se investigó
extensamente sin causa raíz 100% determinada en su
momento — se resolvió después por una causa distinta
(el bug de dmb-button del punto 1), pero si algo
similar aparece en Uroboros contra un dominio .local,
vale la pena revisar el certificado/TLS como
hipótesis temprana, no solo al final.

## Patrón de trabajo recomendado (probado, funciona)

1. Diagnóstico ANTES de escribir cualquier fix —
   pedir al agente que lea el código real, nunca
   asumir comportamiento desde memoria o pseudocódigo
2. Fixes acotados al repositorio correcto — nunca
   escalar a un framework compartido sin agotar
   primero la solución en la capa de aplicación
3. Verificación con evidencia concreta (curl, captura
   de red, reproducción 2/2) antes de aceptar un
   diagnóstico como confirmado
4. Checkpoints de progreso (.kiro/specs/*-progress.md)
   para tareas grandes que puedan interrumpirse por
   consumo de contexto o cortes de conexión
5. Nunca usar exit()/die() en código de aplicación
   sin verificar primero si el test runner comparte
   el mismo proceso PHP (puede matar la suite
   completa silenciosamente)