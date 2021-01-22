# catalog.export
Модуль для экспорта описания, характеристик и изображений каталога

##### Вызов

    <?php
        CModule::IncludeModule('catalog.export');
        $factory = new Сatalog\Export\RendererFactory();
        $export = new Сatalog\Export\CatalogExport1C();
        $export->setRenderer($factory->create())->run();
    ?>
