<div class="assign-list-row" data-assign-row>
    <?= uiSelect('murid_ids[]', 'Nama murid', $studentChoices, ['variant' => 'compact', 'id' => $studentFieldId, 'value' => $studentValue, 'hideLabel' => true]) ?>
    <?= uiButton('Hapus baris murid', 'outline', ['icon' => 'icon_close', 'iconOnly' => true, 'paddingVertical' => 8, 'paddingHorizontal' => 8, 'marginVertical' => 0, 'attributes' => ['data-assign-remove' => true]]) ?>
</div>
