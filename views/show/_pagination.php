<? if ($search && $search->countResultPages() > 1): ?>
    <div class='pagination'>
        <hr>
        <? // left arrow for the previous page ?>
        <? if ((Request::get('page') - ($search->getPagesShown() / 2)) > 0): ?>
            <a href='<?= URLHelper::getLink('', array('search' => $search->query, 'page' => (Request::get('page') - 1))) ?>'> <?= Icon::create('arr_1left') ?></a>
        <? endif; ?>

        <? foreach ($search->getPages(Request::get('page')) as $page): ?>
            <a href='<?= URLHelper::getLink('', array('search' => $search->query, 'page' => $page)) ?>' class='<?= Request::get('page') == $page ? 'current' : ''?>'><?= $page + 1 ?></a>
        <? endforeach; ?>

        <? // right arrow for the next page ?>
        <? if ((Request::get('page') + ($search->getPagesShown() / 2)) < ($search->countResultPages() - 1)): ?>
            <a href='<?= URLHelper::getLink('', array('search' => $search->query, 'page' => (Request::get('page') + 1))) ?>'> <?= Icon::create('arr_1right') ?></a>
        <? endif; ?>
    </div>
<? endif; ?>