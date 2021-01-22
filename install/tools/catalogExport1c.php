<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog.export');

$factory = new Сatalog\Export\RendererFactory();
$export = new Сatalog\Export\CatalogExport1C();
$export->setRenderer($factory->create())->run();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>