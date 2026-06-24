</div>

<div id="contenedorModal"></div>
<?php $page_js = $data['page_js'] ?? []; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!--agregamos el script para los graficos-->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="<?= BASE_URL ?>public/js/Notificaiones.js"></script>
<script src="<?= BASE_URL ?>public/js/Nav.js"></script>
<?php if (!empty($page_js)): ?>
    <?php foreach ($page_js as $js): ?>
        <?php $jsPath = __DIR__ . '/../../public/js/' . $js; ?>
        <!-- Agregamos un parámetro de versión basado en la fecha de modificación del archivo para evitar problemas de caché -->
        <script src="<?= BASE_URL ?>public/js/<?= $js ?>?v=<?= file_exists($jsPath) ? filemtime($jsPath) : time() ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>


<script src="<?= BASE_URL ?>main.js?v=<?= file_exists(__DIR__ . '/../../main.js') ? filemtime(__DIR__ . '/../../main.js') : time() ?>"></script>
</body>

</html>
