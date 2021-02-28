<?php defined('ALTUMCODE') || die() ?>

<div data-link-id="<?= $data->link->link_id ?>" class="col-12 my-2">
    <?php if($data->link->location_url): ?>
    <a href="<?= $data->link->location_url . $data->link->utm_query ?>" data-link-url="<?= $data->link->url ?>" target="_blank">
        <img src="<?= $data->link->settings->image ?>" class="img-fluid rounded" />
    </a>
    <?php else: ?>
    <img src="<?= $data->link->settings->image ?>" class="img-fluid rounded" />
    <?php endif ?>
</div>

