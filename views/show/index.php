<!-- Searchbar -->
<form class="default" onsubmit="return false;" autocomplete="off" novalidate="novalidate">
    <fieldset>
        <legend>
            <?= _('Suche') ?>
            <?= Icon::create('refresh', 'clickable', ['style' => 'position: absolute; right: 0.5em; top: 0.438em'])->asInput(["type" => "submit", "name" => "reset"]) ?>
        </legend>
        <label id="globalsearch">
            <input type="text" name="search" autofocus value="<?= $this->search->query ?>" placeholder="<?= _('Suchbegriff') ?>">
        </label>
    </fieldset>
</form>

<div id="globalsearch">
    <ul id="globalsearch_results">
    <!-- <section id="globalsearch_results" class="search_results">
    </section> -->
    </ul>
</div>
<?= $this->render_partial('show/_pagination.php', array('search' => $this->search)) ?>
