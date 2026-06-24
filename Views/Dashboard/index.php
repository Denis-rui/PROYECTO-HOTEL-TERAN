<?php
$stats = $data['stats'] ?? [];
$notificaciones = $data['notificaciones'] ?? [];

$graficoIngresos = $stats['grafico_ingresos'] ?? [];
$graficoEstados = $stats['grafico_estados_reserva'] ?? [];

$habitacionesLabels = json_encode(['Disponibles', 'Ocupadas', 'Reservadas', 'Mantenimiento', 'En limpieza'], JSON_UNESCAPED_UNICODE);
$habitacionesTotales = json_encode([
  (int) ($stats['habitaciones_disponibles'] ?? 0),
  (int) ($stats['habitaciones_ocupadas'] ?? 0),
  (int) ($stats['habitaciones_reservadas'] ?? 0),
  (int) ($stats['habitaciones_mantenimiento'] ?? 0),
  (int) ($stats['habitaciones_en_limpieza'] ?? 0),
], JSON_UNESCAPED_UNICODE);
$ingresosMeses = json_encode(array_column($graficoIngresos, 'mes'), JSON_UNESCAPED_UNICODE);
$ingresosTotales = json_encode(array_column($graficoIngresos, 'total'), JSON_UNESCAPED_UNICODE);
$estadosLabels = json_encode(array_column($graficoEstados, 'estado'), JSON_UNESCAPED_UNICODE);
$estadosTotales = json_encode(array_column($graficoEstados, 'total'), JSON_UNESCAPED_UNICODE);
?>
<section class="main-content dashboard">
  <header class="main-header">
    <div class="header-left">
      <h1>DASHBOARD - TERAN HOTEL</h1>
    </div>

    <div class="header-right">
      <button id="btnNuevaReserva" class="btn btn-nueva-reserva">
        Nueva Reserva
      </button>
    </div>
  </header>

  <section class="dashboard-widgets-top">
    <div class="widget widget-red">
      <div class="widget-icon">🛏️</div>
      <div class="widget-info">
        <p class="widget-label">Habitaciones disponibles</p>
        <strong id="statHabitacionesDisponibles"><?= htmlspecialchars($stats['habitaciones_disponibles'] ?? 0) ?></strong>
      </div>
    </div>

    <div class="widget widget-green">
      <div class="widget-icon">🗓️</div>
      <div class="widget-info">
        <p class="widget-label">Reservas Activas</p>
        <strong id="statReservasActivas"><?= htmlspecialchars($stats['reservas_activas'] ?? 0) ?></strong>
      </div>
    </div>

    <div class="widget widget-gray">
      <div class="widget-icon">💰📈</div>
      <div class="widget-info">
        <p class="widget-label">Ingreso del día</p>
        <strong id="statIngresoDia">S/ <?= htmlspecialchars(number_format((float) ($stats['ingreso_dia'] ?? 0), 2)) ?></strong>
      </div>
    </div>
  </section>

  <div class="content-columns">
    <div class="column-left">
      <h3 class="section-title">Actividades Recientes</h3>

      <div class="activity-pill pill-blue">
        <div class="activity-icon blue-icon">
          <span>➔</span>
        </div>
        <p>Check in (hoy)</p>
        <strong id="statCheckinsHoy"><?= htmlspecialchars($stats['checkins_hoy'] ?? 0) ?></strong>
      </div>

      <div class="activity-pill pill-purple">
        <div class="activity-icon purple-icon">
          <span>⬅</span>
        </div>
        <p>Check out (hoy)</p>
        <strong id="statCheckoutsHoy"><?= htmlspecialchars($stats['checkouts_hoy'] ?? 0) ?></strong>
      </div>

      <h3 class="section-title">Notificaciones</h3>
      <div id="panelNotificacionesCheckout" class="panel-notificaciones">
        <?php foreach (($notificaciones['notificaciones'] ?? []) as $notificacion): ?>
          <div class="notificacion-dashboard <?= htmlspecialchars($notificacion['prioridad']) ?>">
            <strong><?= htmlspecialchars($notificacion['titulo']) ?></strong>
            <span><?= htmlspecialchars($notificacion['mensaje']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="column-right">
      <section class="card summary-card">
        <div class="card-header">
          <h3 class="summary-title">Resumen del día</h3>
        </div>
        <div class="card-body">
          <ul class="summary-list">
            <li class="summary-item-accordion" id="accordionMantenimiento">
              <div class="summary-item-main">
                <div class="summary-item-left">
                  <span class="summary-icon">🛠️</span>
                  <span>Habitaciones en mantenimiento</span>
                  <span class="chevron-icon">›</span>
                </div>
                <span class="summary-value" id="statMantenimiento"><?= htmlspecialchars($stats['habitaciones_mantenimiento'] ?? 0) ?></span>
              </div>

              <div class="mante-accordion-content" id="manteDetalle">
                <?php if (!empty($stats['detalles_mantenimiento'])): ?>
                  <div class="mante-grid">
                    <?php foreach ($stats['detalles_mantenimiento'] as $det): ?>
                      <div class="mante-row">
                        <span class="mante-hab">Hab. <?= htmlspecialchars($det['numero_habitacion']) ?>:</span>
                        <span class="mante-razon"><?= htmlspecialchars($det['motivo']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="mante-vacio">No hay habitaciones en mantenimiento.</p>
                <?php endif; ?>
              </div>
            </li>
            <li>
              <div class="summary-item-left">
                <span class="summary-icon">📍</span>
                <span>Lugar de procedencia</span>
              </div>
              <span class="summary-value"><?= htmlspecialchars($stats['total_procedencias'] ?? 0) ?></span>
            </li>
            <li>
              <div class="summary-item-left">
                <span class="summary-icon">🏠</span>
                <span>Estancia mas corta(dias)</span>
              </div>
              <span class="summary-value"><?= htmlspecialchars($stats['estancia_minima'] ?? 0) ?></span>
            </li>
            <li>
              <div class="summary-item-left">
                <span class="summary-icon">💰</span>
                <span>Ingreso del dia</span>
              </div>
              <span class="summary-value">S/ <?= number_format($stats['ingreso_dia'] ?? 0, 2) ?></span>
            </li>
          </ul>
        </div>
      </section>
    </div>
  </div>

  <section class="dashboard-graficos">
    <div class="grafico-card">
      <h3 class="grafico-titulo">Estado de habitaciones</h3>
      <canvas id="graficoHabitaciones"></canvas>
    </div>

    <div class="grafico-card">
      <h3 class="grafico-titulo">Ingresos acumulados (ultimos 6 meses)</h3>
      <?php if (empty($graficoIngresos)): ?>
        <p class="grafico-vacio">Sin datos de ingresos registrados aun.</p>
      <?php else: ?>
        <canvas id="graficoIngresos"></canvas>
      <?php endif; ?>
    </div>

    <div class="grafico-card">
      <h3 class="grafico-titulo">Reservas por estado</h3>
      <?php if (empty($graficoEstados)): ?>
        <p class="grafico-vacio">Sin reservas registradas aun.</p>
      <?php else: ?>
        <canvas id="graficoEstados"></canvas>
      <?php endif; ?>
    </div>
  </section>

  <script>
    window.DASHBOARD_GRAFICOS = {
      habitaciones: {
        labels: <?= $habitacionesLabels ?: '[]' ?>,
        totales: <?= $habitacionesTotales ?: '[]' ?>
      },
      ingresos: {
        meses: <?= $ingresosMeses ?: '[]' ?>,
        totales: <?= $ingresosTotales ?: '[]' ?>
      },
      estados: {
        labels: <?= $estadosLabels ?: '[]' ?>,
        totales: <?= $estadosTotales ?: '[]' ?>
      }
    };
  </script>

  <?php require_once("Views/Template/Modals/Modal-NuevaReserva.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Pago.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Comprobante.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>
