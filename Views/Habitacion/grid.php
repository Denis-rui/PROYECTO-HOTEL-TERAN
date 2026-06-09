<?php $habitaciones = $data['habitaciones'] ?? []; ?>
<?php if (!empty($habitaciones) && is_array($habitaciones)): ?>
    <?php
    $porPiso = [];
    foreach ($habitaciones as $hab) {
        $porPiso[$hab['piso']][] = $hab;
    }
    ksort($porPiso);
    ?>
    <?php foreach ($porPiso as $piso => $habitacionesPiso): ?>
        <h3 class="titulo-piso">Piso <?= htmlspecialchars($piso) ?></h3>
        <div class="carrusel-piso-wrapper">
            <button class="btn-carrusel btn-carrusel-izq" onclick="desplazarCarrusel(this, -1)"
                title="Anterior">&#10094;</button>
            <div class="grupo-piso carrusel-piso">
                <?php foreach ($habitacionesPiso as $hab): ?>
                    <?php
                    $estadoBD = $hab['estado'] ?? '';
                    $esLimpieza = (strtolower($estadoBD) === 'en limpieza') && !empty($hab['limpieza_inicio']);

                    // Para la tarjeta, "En Limpieza" se muestra como "mantenimiento" (estilo naranja)
                    if ($esLimpieza) {
                        $claseEstado = 'mantenimiento';
                    } else {
                        $claseEstado = strtolower($estadoBD);
                        if (strpos($claseEstado, 'mantenim') !== false)
                            $claseEstado = 'mantenimiento';
                    }

                    $segundosRestantes = 0;
                    if ($esLimpieza) {
                        $inicio = strtotime($hab['limpieza_inicio']);
                        $fin = $inicio + 3600;
                        $segundosRestantes = max(0, $fin - time());
                    }
                    ?>
                    <div class="tarjeta-habitacion <?= $claseEstado ?>" <?= $esLimpieza ? 'data-limpieza-id="' . (int) $hab['id'] . '" data-segundos="' . $segundosRestantes . '"' : '' ?>>
                        <div class="habitacion-cabecera">
                            <div class="habitacion-numero"><?= htmlspecialchars($hab["numero_habitacion"]); ?></div>
                            <div class="habitacion-cabecera-botones">
                                <button class="btn-editar-habitacion" title="Editar habitación"
                                    onclick="editarHabitacion(this, <?= (int) $hab['id'] ?>, '<?= htmlspecialchars($hab['numero_habitacion'], ENT_QUOTES) ?>', <?= (int) $hab['piso'] ?>, <?= (int) $hab['id_tipo_habitacion'] ?>, <?= (int) $hab['capacidad'] ?>, '<?= htmlspecialchars($hab['descripcion'] ?? '', ENT_QUOTES) ?>')">
                                    ✏️
                                </button>
                                <button class="btn-eliminar-habitacion" title="Eliminar habitación"
                                    onclick="eliminarHabitacion(<?= (int) $hab['id'] ?>, '<?= htmlspecialchars($hab['numero_habitacion'], ENT_QUOTES) ?>')">
                                    🗑️
                                </button>
                            </div>
                        </div>

                        <div class="habitacion-tipo"><?= htmlspecialchars($hab['tipo_nombre']); ?> · Cap.
                            <?= htmlspecialchars($hab['capacidad']); ?></div>
                        <div class="habitacion-precio">S/ <?= number_format($hab['precio'], 0); ?> / Dia</div>
                        <div class="habitacion-descripcion"><?= htmlspecialchars($hab['descripcion'] ?: 'Sin descripción'); ?></div>

                        <div class="habitacion-status-badges">
                            <div class="badge-status operativo">
                                <?= $esLimpieza ? 'Mantenimiento' : ucfirst(htmlspecialchars($estadoBD)) ?>
                            </div>
                        </div>

                        <?php if ($esLimpieza): ?>
                            <div class="limpieza-timer-bloque">
                                <div class="limpieza-icono">🧹 En Limpieza</div>
                                <div class="limpieza-countdown" id="timer-<?= (int) $hab['id'] ?>">
                                    <?php
                                    $mins = floor($segundosRestantes / 60);
                                    $secs = $segundosRestantes % 60;
                                    echo sprintf('%02d:%02d', $mins, $secs);
                                    ?>
                                </div>
                                <button class="btn-terminar-limpieza"
                                    onclick="terminarLimpieza(<?= (int) $hab['id'] ?>, '<?= htmlspecialchars($hab['numero_habitacion'], ENT_QUOTES) ?>')">
                                    ✅ Terminé antes
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="habitacion-acciones">
                                <select class="selector-estado" onchange="cambiarEstado(<?= (int) $hab['id'] ?>, this.value)">
                                    <option value="" disabled selected>Cambiar estado</option>
                                    <option value="Disponible">Disponible</option>
                                    <option value="Mantenimiento">Mantenimiento</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn-carrusel btn-carrusel-der" onclick="desplazarCarrusel(this, 1)"
                title="Siguiente">&#10095;</button>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 10px; margin-top: 20px;">
        No se encontraron habitaciones con los filtros seleccionados.
    </div>
<?php endif; ?>
