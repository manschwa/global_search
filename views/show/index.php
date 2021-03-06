<!-- Searchbar -->
<form class="default" novalidate="novalidate">
    <fieldset>
        <label>
            <input type="text" name="search" autofocus value="<?= $this->search->query ?>" placeholder="<?= _('Suchbegriff') ?>">
        </label>
    </fieldset>
    <footer>
        <?= \Studip\Button::create(_('Suchen'), 'searching')?>
        <?= \Studip\Button::create(_('Zurücksetzen'), 'reset')?>
    </footer>
</form>

<? if ($this->search->query): ?>
    <? if ($this->search->error): ?>
        <p><?= htmlReady($this->search->error) ?></p>
    <? else: ?>
        <h3><?= sprintf(_('Suchergebnisse für "%s"'), htmlReady($this->search->query)) ?></h3>
    <? endif; ?>
<? endif; ?>

<? if ($this->search->results): ?>
    <section class="search_results">
        <? foreach ($this->search->resultPage(Request::get('page')) as $result): ?>
            <article>
                <hr>
                <p class="result_type"><?= $result['name'] ?></p>
                <?
                // only load avatars for the displayed results
                $indexClass = 'IndexObject_' . ucfirst($result['type']);
                $result['avatar'] = $indexClass::getAvatar($result);
                ?>
                <p class="avatar"><?= $result['avatar'] ?></p>
                <a href="<?= URLHelper::getURL($result['link']) ?>">
                <? if (in_array($result['type'], array('document', 'forumentry'))): ?>
                    <? $seminar = Course::find($result['range2']) ?>
                    <?= htmlReady($seminar['name'] . ': ') ?>
                <? endif; ?>
                <?= htmlReady($result['title']) ?></a>
                <?= $this->search->getInfo($result, $this->search->query) ?>
            </article>
        <? endforeach; ?>
    </section>
<? elseif ($this->search->query && !$this->search->error): ?>
    <?= _('Leider keine Treffer.') ?>
<? endif; ?>
<?= $this->render_partial('show/_pagination.php', array('search' => $this->search)) ?>
