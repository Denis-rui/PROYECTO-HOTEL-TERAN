<?php $habitaciones = $data['habitaciones'] ?? []; ?>
<?php if (!empty($habitaciones) && is_array($habitaciones)): ?>
    <?php $pisoActual = null; ?>
    <?php foreach ($habitaciones as $hab): ?>
        <?php if ($pisoActual !== $hab['piso']): ?>
            <?php if ($pisoActual !== null): ?></div><?php endif; ?>
            <?php $pisoActual = $hab['piso']; ?>
            <h3 class="titulo-piso">Piso <?= htmlspecialchars($pisoActual) ?></h3>
            <div class="grupo-piso">
        <?php endif; ?>
        
        <?php 
            $claseEstado = strtolower(htmlspecialchars($hab['estado']));
            if (strpos($claseEstado, 'mantenim') !== false) $claseEstado = 'mantenimiento';
        ?>
        
        <div class="tarjeta-habitacion <?= $claseEstado ?>">
            <div class="habitacion-cabecera">
                <div class="habitacion-numero"><?= htmlspecialchars($hab["numero_habitacion"]); ?></div>
                <div class="habitacion-cabecera-botones">
                    <button
                        class="btn-editar-habitacion"
                        title="Editar habitación"
                        onclick="editarHabitacion(this, <?= (int) $hab['id'] ?>, '<?= htmlspecialchars($hab['numero_habitacion'], ENT_QUOTES) ?>', <?= (int) $hab['piso'] ?>, <?= (int) $hab['id_tipo_habitacion'] ?>, <?= (int) $hab['capacidad'] ?>, '<?= htmlspecialchars($hab['descripcion'] ?? '', ENT_QUOTES) ?>')">
                        ✏️
                    </button>
                    <button
                        class="btn-eliminar-habitacion"
                        title="Eliminar habitación"
                        onclick="eliminarHabitacion(<?= (int) $hab['id'] ?>, '<?= htmlspecialchars($hab['numero_habitacion'], ENT_QUOTES) ?>')">
                        🗑️
                    </button>
                </div>
            </div>
            <div class="habitacion-tipo"><?= htmlspecialchars($hab['tipo_nombre']); ?> · Cap. <?= htmlspecialchars($hab['capacidad']); ?></div>
            <div class="habitacion-precio">S/ <?= number_format($hab['precio'], 0); ?> / Dia</div>
            <div class="habitacion-descripcion"><?= htmlspecialchars($hab['descripcion'] ?: 'Sin descripción'); ?></div>
            
            <div class="habitacion-status-badges">
                <div class="badge-status operativo"><?= ucfirst(htmlspecialchars($hab['estado'])); ?></div>
            </div>

            <div class="habitacion-acciones">
                <select class="selector-estado" onchange="cambiarEstado(<?= (int) $hab['id'] ?>, this.value)">
                    <option value="" disabled selected>Cambiar estado</option>
                    <option value="Disponible">Disponible</option>
                    <option value="Ocupada">Ocupada</option>
                    <option value="Mantenimiento">Mantenimiento</option>
                    <option value="Reservada">Reservada</option>
                </select>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 10px; margin-top: 20px;">
        No se encontraron habitaciones con los filtros seleccionados.
    </div>
<?php endif; ?>
