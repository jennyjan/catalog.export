<?
global $MESS;
$PathInstall = str_replace("\\", "/", __FILE__);
$PathInstall = substr($PathInstall, 0, strlen($PathInstall)-strlen("/index.php"));
IncludeModuleLangFile($PathInstall."/install.php");

Class catalog_export extends CModule
{
    var $MODULE_ID = "catalog.export";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;

    function catalog_export()
    {
        $arModuleVersion = array();

        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path."/version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }
        else
        {
            $this->MODULE_VERSION = "1.0.0";
            $this->MODULE_VERSION_DATE = "2019-09-10 12:00:00";
        }

        $this->MODULE_NAME = "Экспорт каталога";
        $this->MODULE_DESCRIPTION = "";
    }

    function InstallDB($arParams = array())
    {
        return true;
    }

    function UnInstallDB($arParams = array())
    {
        return true;
    }

    function InstallEvents()
    {
        return true;
    }

    function UnInstallEvents()
    {
        return true;
    }

    function InstallFiles()
    {
        CopyDirFiles(__DIR__ . '/admin',$_SERVER["DOCUMENT_ROOT"] . '/bitrix/admin', true, true);               
        CopyDirFiles(__DIR__ . '/js',$_SERVER["DOCUMENT_ROOT"].'/bitrix/js', true, true);           
        CopyDirFiles(__DIR__ . '/tools',$_SERVER["DOCUMENT_ROOT"]."/bitrix/tools/custom.handlers", true, true);     
        return true;
    }

    function UnInstallFiles()
    {
        DeleteDirFiles(__DIR__ . '/admin',$_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');                       
        DeleteDirFiles(__DIR__ . '/tools',$_SERVER["DOCUMENT_ROOT"]."/bitrix/tools/custom.handlers");
        DeleteDirFilesEx('/bitrix/js/catalog.export');
        return true;    
    }

    function DoInstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        $this->InstallDB();
        $this->InstallFiles();
        RegisterModule("catalog.export");
        $APPLICATION->IncludeAdminFile(GetMessage("COMPRESS_INSTALL_TITLE"), $DOCUMENT_ROOT."/bitrix/modules/catalog.export/install/step.php");
    }

    function DoUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        $this->UnInstallDB();
        $this->UnInstallFiles();
        UnRegisterModule("catalog.export");
        $APPLICATION->IncludeAdminFile(GetMessage("COMPRESS_UNINSTALL_TITLE"), $DOCUMENT_ROOT."/bitrix/modules/catalog.export/install/unstep.php");
    }
    
}
?>