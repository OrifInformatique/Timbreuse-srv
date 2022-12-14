<div class="items_list container">
    <div class="row mb-2">
        <div class="col-sm-8 text-left">
            <!-- Display list title if defined defined -->
            <?= isset($list_title) ? '<h3>' . esc($list_title) . '</h3>' : '' ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <?php foreach ($columns as $column) : ?>
                        <th><?= ucfirst($column) ?></th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <?php foreach ($items as $item) : ?>
                <tr>
                    <th><a href="<?= $item['url'] ?>"><?= $item['label_week'] ?></a></th>
                    <td><?= $item['time'] ?></td>
                    <td><?= $item['modifyTime'] ?></td>
                </tr>
            <?php endforeach ?>
        </table>
    </div>
</div>