</div>

    <div id="contenedorModal"></div>
    <?php $page_js = $data['page_js'] ?? []; ?>

    <script src="<?= BASE_URL ?>public/js/Notificaiones.js"></script>
    <script src="<?= BASE_URL ?>public/js/Nav.js"></script>
    <?php if (!empty($page_js)): ?>
        <?php foreach ($page_js as $js): ?>
            <script src="<?= BASE_URL ?>public/js/<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>