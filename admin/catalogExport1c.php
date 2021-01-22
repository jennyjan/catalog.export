<?php
set_time_limit(0);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
CJSCore::Init(array("jquery"));
$APPLICATION->AddHeadScript('/bitrix/js/catalog.export/catalogExport1c.js');

$POST_RIGHT = $APPLICATION->GetGroupRight("main");
if ($POST_RIGHT == "D") {
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}

$aTabs = array(array("DIV" => "edit1", "TAB" => "Экспорт каталога"));
$tabControl = new CAdminTabControl("tabControl", $aTabs);
$APPLICATION->SetTitle("Экспорт каталога для 1С");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");?>
<form method="post" action="<?echo $APPLICATION->GetCurPage()?>" enctype="multipart/form-data" name="post_form" id="post_form">
<?
$tabControl->Begin();
$tabControl->BeginNextTab();
?>
    <tr style="float: left; margin-bottom: 10px;">
        <td>
            <input type="checkbox" value="yes" id="1c_exchange_filter" style="margin-right: 10px;"/>
        </td>
        <td>
            <label>Не учитывать выгрузку 1С</label>
        </td> 
    </tr>
    <tr style="width: 100%; float: left; margin-bottom: 10px;">
        <td>
            <input type="checkbox" value="yes" id="package_upload" checked style="margin-right: 10px;"/>
        </td>
        <td>
            <label>Включить выгрузку пакетами</label>
        </td> 
    </tr>
    <tr id="record_count_tr" style="width: 100%; float: left; margin-bottom: 10px;">
        <td>
            <label>Кол-во пакетов</label>
        </td>
        <td>
            <input type="input" value="100" id="record_count_filter" style="margin-right: 10px;"/>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <input type=button value="Старт" id="work_start" />
            <div id="result" style="padding-top:10px"></div>
        </td>
    </tr>
<?
$tabControl->End();
?>
</form>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");?>