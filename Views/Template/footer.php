</div>

<div id="contenedorModal"></div>
<?php $page_js = $data['page_js'] ?? []; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= BASE_URL ?>public/js/Notificaiones.js"></script>
<script src="<?= BASE_URL ?>public/js/Nav.js"></script>
<?php if (!empty($page_js)): ?>
    <?php foreach ($page_js as $js): ?>
        <script src="<?= BASE_URL ?>public/js/<?= $js ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>


<script src="<?= BASE_URL ?>main.js"></script>
</body>

</html>