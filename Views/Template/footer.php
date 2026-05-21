</div>

    <div id="contenedorModal"></div>

    <script src="<?= BASE_URL ?>public/js/Notificaiones.js"></script>
    <script src="<?= BASE_URL ?>public/js/Nav.js"></script>
    <?php if (!empty($data['page_js'])): ?>
        <?php foreach ($data['page_js'] as $js): ?>
            <script src="<?= BASE_URL ?>public/js/<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?= BASE_URL ?>main.js"></script>
</body>
</html>