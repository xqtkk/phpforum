<!-- ====================== FILTERS ====================== -->
<div class="filters">
    <form method="GET" class="filter-form">
        <select name="category" class="filter-select">
            <option value="">Все категории</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($category == $c['id'] ? "selected" : "") ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort" class="filter-select">
            <option value="">По умолчанию</option>
            <option value="likes">По лайкам</option>
            <option value="dislikes">По дизлайкам</option>
            <option value="comments">По комментариям</option>
            <option value="date_old">Старые сначала</option>
        </select>

        <button class="filter-btn">Применить</button>
    </form>
</div>