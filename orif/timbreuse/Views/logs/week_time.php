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
                    <th>
                        <?= ucfirst(lang('tim_lang.hourly')) ?>
                    </th>
                    <th></th>
                    <?php foreach ($items as $key => $item) : ?>
                        <th><a href="<?= $item['url'] ?>"><?= ucfirst(lang('tim_lang.' . $key)) . ' ' . $item['dayNb']  ?></a></th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $halfKey => $row) : ?>
                    <?php foreach ($rows2 as $typeKey => $type) : ?>
                        <?php if (!(($halfKey == array_key_last($rows)) and ($typeKey != array_key_first($rows2)))) : ?>
                            <tr>
                                <th><?= $typeKey == array_key_first($rows2) ? $row : '' ?></th>
                                <td><?= $halfKey == array_key_last($rows) ? '' : ucfirst($type) ?></td>
                                <?php foreach ($items as $item) : ?>
                                    <td>
                                        <?php if (isset($item[$halfKey])) : ?>
                                            <p><?= $item[$halfKey][$typeKey] ?></p>
                                        <?php elseif (isset($item['time'])) : ?>
                                            <?= $item['time'] ?>
                                        <?php endif ?>
                                    </td>
                                <?php endforeach ?>
                            </tr>
                        <?php endif ?>
                    <?php endforeach ?>
                <?php endforeach ?>
            </tbody>
            <tfoot>
                <tr>
                    <th><?= ucfirst(lang('tim_lang.weekTime')) ?></th>
                    <td><?= $sumTime ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <p>
        <?= $isFakeLog ? lang('tim_lang.msgAsterisk') : '' ?>
    </p>
</div>