<?php
$mediaRowSequence = ($mediaRowSequence ?? 0) + 1;
$mediaId = 'school-media-' . $mediaRowSequence;
?>
<div class="sekolah-media-edit-row" data-assign-row>
    <?= uiSelect('media_jenis[]', 'Jenis Media', array_combine(['Instagram', 'Facebook', 'TikTok', 'YouTube', 'Website'], ['Instagram', 'Facebook', 'TikTok', 'YouTube', 'Website']), ['id' => $mediaId . '-jenis', 'value' => $item['jenis_media'] ?? 'Instagram']) ?>
    <?= uiField('media_nama[]', 'Nama Akun', ['id' => $mediaId . '-nama', 'variant' => 'form', 'font' => 'geist', 'value' => $item['nama_akun'] ?? '', 'placeholder' => 'Isi ID/nama akun']) ?>
    <?= uiField('media_url[]', 'URL / Link Media', ['id' => $mediaId . '-url', 'variant' => 'form', 'font' => 'geist', 'value' => $item['url'] ?? '', 'placeholder' => 'Isi URL/link media']) ?>
    <?= uiButton('Hapus media', 'outline', ['icon' => 'icon_trash', 'iconOnly' => true, 'marginVertical' => 0, 'attributes' => ['data-assign-remove' => true]]) ?>
</div>
