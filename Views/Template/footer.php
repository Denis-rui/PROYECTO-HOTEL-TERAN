</div>

    <div id="contenedorModal"></div>
    <?php $page_js = $data['page_js'] ?? []; ?>
    <?php $versionCache = time(); ?>

    <script src="<?= BASE_URL ?>public/js/Notificaiones.js?v=<?= $versionCache ?>"></script>
    <script src="<?= BASE_URL ?>public/js/Nav.js?v=<?= $versionCache ?>"></script>
    <?php if (!empty($page_js)): ?>
        <?php foreach ($page_js as $js): ?>
            <script src="<?= BASE_URL ?>public/js/<?= $js ?>?v=<?= $versionCache ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?= BASE_URL ?>main.js"></script>
</body>
</html>