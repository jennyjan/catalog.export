<?php
namespace Сatalog\Export;

use \Datetime;
use \ZipArchive;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Config\Option;

interface IRenderer {
    public function render($data);
}

Class CatalogExport1C
{
    const IBLOCK_CONFIGURE_ID = 1;
    const IBLOCK_CATALOG_ID = 2;
    const IBLOCK_CHARACTERISTICS_ID = 3;
    const IMAGE_QUALITY = 80;
    private $date;
    private $exportPath;
    private $rootPath;
    private $fullExportPath;
    private $filter;

    public function __construct($exportPath = '/bitrix/catalog_export/export_1c')
    {
        $this->date = new DateTime();
        $this->exportPath = $exportPath;        
        $this->rootPath = $_SERVER["DOCUMENT_ROOT"];
        $this->fullExportPath = $this->rootPath.$this->exportPath;
        $this->filter = trim($_REQUEST["1C_EXCHANGE_FILTER"]);
        $this->packageUpload = trim($_REQUEST["PACKAGE_UPLOAD"]);
        $this->packageCount = trim(htmlspecialchars($_REQUEST["RECORDS_COUNT_FILTER"]));
        $this->lastId = trim(htmlspecialchars($_REQUEST["LAST_ID"]));
    }
    
    public function setRenderer(IRenderer $renderer)
    {
        $this->renderer = $renderer;
        return $this;
    }

    private function getActiveCatalogSections()
    {
        $sections = [];
        $arFilter = array(
            "IBLOCK_ID" => self::IBLOCK_CATALOG_ID, 
            "IBLOCK_ACTIVE" => "Y", 
            "ACTIVE" => "Y", 
            "GLOBAL_ACTIVE" => "Y", 
            "left_margin" => "asc"
        );

        $arSelect = array(
            "ID", 
            "NAME", 
            "CODE", 
            "TIMESTAMP_X", 
            "DEPTH_LEVEL"
        );
        
        $rsSection = \CIBlockSection::GetList(Array(), $arFilter, false, $arSelect);
        while ($arSection = $rsSection->GetNext()) { 
            $sections[] = $arSection;  
        }
        return $sections;
    }

    private function getActiveCatalogElements($sections)
    {
        $arResult = [];
        $date = clone $this->date;
        $dateTime = Option::get("catalog.export", "last_succes_export_to_1c");
        
        $arSelect = array(
            "ID", "NAME", "CODE", "IBLOCK_ID", "IBLOCK_SECTION_ID",
            "DETAIL_TEXT", "DETAIL_PICTURE", "PREVIEW_TEXT",
            "PREVIEW_PICTURE", "PROPERTY_66", "PROPERTY_69",
            "PROPERTY_170", "PROPERTY_98", "PROPERTY_93"
        );

        $arFilter = array(
            "IBLOCK_ID" => self::IBLOCK_CATALOG_ID,
            "SITE_DIR" => SITE_ID,
            "IBLOCK_ACTIVE" => "Y",
            "ACTIVE_DATE" => "Y",
            "ACTIVE" => "Y",
            "CHECK_PERMISSIONS" => "Y"
        );
        
        if ($dateTime) {    
            $arFilter['>=TIMESTAMP_X'] = ConvertTimeStamp($dateTime, "FULL");
        }
      
        if ($this->filter) {    
            $arFilter['!MODIFIED_BY'] = 1234; // исключить правки пользователя
        }
        
        $sectionCodes = array_column($sections, 'CODE');     
        $arFilter["SECTION_CODE"] = $sectionCodes;
        
        if ($this->packageUpload && !empty($this->packageCount)) {
            $arSort = array("ID" => "ASC");
            $arNavParams['nTopCount'] = $this->packageCount;
            if ($this->lastId) {
                $arFilter['>ID'] = $this->lastId;
                $uploadPercent = $this->getUploadPercent($this->lastId);
            }
            $arResult["PERCENT"] = (isset($uploadPercent) && $uploadPercent > 0 ) ? $uploadPercent : 0;
        }

        $rsElements = \CIBlockElement::GetList($arSort, $arFilter, false, $arNavParams, $arSelect);
        while ($obElement = $rsElements->GetNextElement()) {
            $arGet = $obElement->GetFields();    
            $arItem = [];           
            $arDetailPicture = \CFile::GetFileArray($arGet["DETAIL_PICTURE"]);

            foreach ($arGet["PROPERTY_69_VALUE"] as $arPicture) {
                $arMorePicture = \CFile::GetFileArray($arPicture);
                $arItem["IMG"]["MORE_PICTURES"][] = $arMorePicture["SRC"];
            }                     
                       
            $arItem["ID"] = $arGet["ID"];
            $arItem["DESCR"] = $arGet["DETAIL_TEXT"]; 
            $arItem["IMG"]["DETAIL_PICTURE"] = $arDetailPicture["SRC"];

            if($arGet["PROPERTY_66_VALUE"]["2"]) {
                $arItem["CODE"] = trim($arGet["PROPERTY_66_VALUE"]["2"]);
            }

            while (!($linkRes = $this->getSpecificationProductsLink($arGet["IBLOCK_SECTION_ID"])) && $arGet["IBLOCK_SECTION_ID"]) {
                $arGet["IBLOCK_SECTION_ID"] = $this->getParentSectionId($arGet["IBLOCK_SECTION_ID"]);
            }
           
            if ($linkRes) {
                $arItem['characteristics'] = $this->getCharacteristicsValue(
                    self::IBLOCK_CATALOG_ID, 
                    $arGet["ID"], 
                    $linkRes["PROPERTY_SPECIFICATION_VALUE"]
                );
            }
            $arResult["ITEMS"][] = $arItem;
            $arResult["LAST_ID"] = $arItem["ID"];
        }      
        return $arResult;
    }
    
    private function getUploadPercent($lastId)
    {
        $rsLeftBorder = \CIBlockElement::GetList(array("ID" => "ASC"), array("IBLOCK_ID" => self::IBLOCK_CATALOG_ID, "<=ID" => $lastId));
        $leftBorderCnt = $rsLeftBorder->SelectedRowsCount();
        $rsAll = \CIBlockElement::GetList(array("ID" => "ASC"), array("IBLOCK_ID" => self::IBLOCK_CATALOG_ID));
        $allCnt = $rsAll->SelectedRowsCount();
        $uploadPercent = round(100*$leftBorderCnt/$allCnt, 2);
        return $uploadPercent;
    }
    
    private function getParentSectionId($sectionId) {
        $result = \CIBlockSection::GetByID($sectionId);    
        if ($arRes = $result->GetNext()) {
            if ($arRes['DEPTH_LEVEL'] == 1) {
                $parentSectionId = false;
            } else {
                $parentSectionId = $arRes['IBLOCK_SECTION_ID'];
            }
            return $parentSectionId;
        }    
    }
    
    private function createDir($dir)
    {
        if(!is_dir($dir)) {
            mkdir($dir, 0700);
        }           
    }
         
    private function saveImgs($arItems)
    {
        $result = '';
        $this->createDir(sprintf('%s/imgcopy/', $this->fullExportPath));
        foreach ($arItems as $arItem) {
                    
            if ($arItem["IMG"]["DETAIL_PICTURE"]) {               
                $file = $this->rootPath.$arItem["IMG"]["DETAIL_PICTURE"];    
                $ext = end(explode(".", $file));
                $newPhoto = sprintf('%s/imgcopy/%s.%s', $this->fullExportPath, $arItem['CODE'], $ext);
                $result .= $this->copyFile($file, $newPhoto);
                
                $newfileName = sprintf('%s/imgcopy/%s', $this->fullExportPath, $arItem['CODE']);               
                $this->convertImgToJpg($ext, $newPhoto, self::IMAGE_QUALITY, $newfileName);              
            } 

            if ($arItem["IMG"]["MORE_PICTURES"]) {
                $j = 0;
                foreach ($arItem["IMG"]["MORE_PICTURES"] as $arItem["PICTURE"]) {
                    $j++;                
                    $file = $this->rootPath.$arItem["PICTURE"];
                    $ext = end(explode(".", $file));
                    $newPhoto = sprintf('%s/imgcopy/%s.%d.%s', $this->fullExportPath, $arItem['CODE'], $j, $ext);
                    $result .= $this->copyFile($file, $newPhoto);
                     
                    $newfileName = sprintf('%s/imgcopy/%s.%d', $this->fullExportPath, $arItem['CODE'], $j);               
                    $this->convertImgToJpg($ext, $newPhoto, self::IMAGE_QUALITY, $newfileName);                      
                }
            }                
        }
        if ($result) {
            Debug::writeToFile($result, "", sprintf('%s/catalogExport1C-%s.log', $this->exportPath, $this->date->getTimestamp()));
        }     
        return $result;
    }

    private function copyFile($file, $newPhoto)
    {
        $response = '';
        if (!copy($file, $newPhoto)) {
            $errors = error_get_last();
            $response = sprintf('Не удалось скопировать %s...Error type:%s. Error message:%s.\n', 
                $file, $errors['type'], $errors['message']
            );   
        } else {
            $response = "Файл $file скопирован.\n";
        }       
        return $response;
    }
    
    private function createZipArchive()
    {
        $zipName = sprintf('%s/catalogExport1c-%s.zip', $this->fullExportPath, $this->date->getTimestamp());
        $zipLink = str_replace($this->rootPath, '', $zipName);
        $realPath = realpath($this->fullExportPath);
        $zip = new ZipArchive();
        $zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) { 
            $ext = end(explode(".", $file));
            if (!$file->isDir() && $ext != 'zip' && $ext != 'log') {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($realPath) + 1);
                $zip->addFile($filePath, $relativePath);

                if ($file->getFilename() != $zipName) {
                    $filesToDelete[] = $filePath;
                }    
            }
        }
        $zip->close();
        $this->deleteFiles($filesToDelete);
        Option::set("catalog.export", "last_succes_export_to_1c", $this->date->getTimestamp());
        if (file_exists($zipName)) {
            return $zipLink;           
        } else {
            return 0;            
        }       
    }

    private function deleteFiles($dataToDelete)
    {
        if (is_array($dataToDelete)) {
            foreach ($dataToDelete as $file) {
                unlink($file);
            }
        } else {
            unlink($dataToDelete);
        }        
    }
    
    private function prepareDataToExport($arItems)
    {
        $arResult = [];
        $content = '';
        $this->createDir(sprintf('%s/descriptions/', $this->fullExportPath));
        foreach ($arItems as $arItem) {
            if (!empty($arItem['characteristics']) || !empty($arItem["DESCR"])) {
                $content = '<table class="specification">';
                foreach ($arItem['characteristics'] as $propSection) {
                    foreach ($propSection["VALUES"] as $val) {
                        $content .= sprintf('<tr>
                            <td style="border-bottom: 1px dashed #E0E0E0;">%s</td>
                            <td style="border-bottom: 1px dashed #E0E0E0;">%s</td>
                            </tr>', $val["NAME"], $val["VALUE"]
                        );
                    }
                }
                $content .= '</table>'.$arItem["DESCR"];
                $fileName = sprintf('%s/descriptions/%s.html', $this->fullExportPath, $arItem['CODE']);            
                $this->writeToFile($fileName, $content);
            }    
        }
    }
    
    private function writeToFile($fileName, $content)
    {
        $response = '';
        if ($content && $fileName) {
            $fp = fopen($fileName, 'w');
            $write = fwrite($fp, $content); 
            if ($write) {
                $response .= 'Данные в файл '.$fileName.' успешно занесены.';
            } else {
                $response .= 'Ошибка при записи в файл '.$fileName.".";
            }                      
            fclose($fp);
            if ($response) {
                Debug::writeToFile($response, "", sprintf('%s/catalogExport1C-%s.log', $this->exportPath, $this->date->getTimestamp()));
            } 
        } 
    }
    
    private function getSpecificationProductsLink($id)
    {     
        /* PROPERTY_products - товарная группа */
        $rs = \CIBlockElement::GetList(
            array(), 
            array("ACTIVE" => "Y", "IBLOCK_ID" => self::IBLOCK_CONFIGURE_ID, 
                "PROPERTY_products" => $id), 
            false, 
            array("nTopCount" => 1), 
            array("PROPERTY_specification")
        );      
        if ($ar = $rs->GetNext()) {
            return $ar; 
        } else {
            return false;
        }
    }

    private function getCharacteristicsValue($iblockId, $elementId, $specificId) 
    {
        $resProp = [];
        $characteristics = [];

        $dbProps = \CIBlockElement::GetProperty(
            $iblockId, 
            $elementId,
            array("sort" => "asc"), 
            Array("CODE" => "specification")
        );

        while ($arProps = $dbProps->GetNext()) {
            $resProp[] = (int)($arProps["VALUE"]);
        }
                      
        $res2 = \CIBlockSection::GetList(
            Array("SORT" => "ASC"), 
            Array("SECTION_ID" => $specificId), 
            false
        );

        while ($arRes2 = $res2->GetNext()) {
            $val = [];
            $res3 = \CIBlockSection::GetList(
                Array("SORT" => "ASC"), 
                Array("SECTION_ID" => $arRes2["ID"]), 
                false
            ); 

            while ($arRes3 = $res3->GetNext()) {                              
                $res4 = \CIBlockElement::GetList(
                    Array("SORT" => "ASC"), 
                    array("ACTIVE" => "Y", "IBLOCK_ID" => self::IBLOCK_CHARACTERISTICS_ID, "SECTION_ID" => $arRes3["ID"]), 
                    false
            );

                while ($arRes4 = $res4->GetNext()) {                    
                    if (in_array($arRes4["ID"], $resProp)) {
                        $val[] = Array("NAME" => $arRes3["NAME"],
                            "VALUE" => $arRes4["NAME"]);   
                    }               
                }                           
            }
            
            if (count($val) > 0) { 
                $characteristics[] = Array("NAME" => $arRes2["NAME"], "VALUES" => $val);
            }  
        }
        return $characteristics;
    }
    
    private function convertImgToJpg($ext, $filePath, $quality, $newfileName) 
    {  
        if (preg_match('/jpg|jpeg/i', $ext)) {
            return false;
        } elseif (preg_match('/png/i', $ext)) {
            $image = imagecreatefrompng($filePath);
        } elseif (preg_match('/gif/i', $ext)){
            $image = imagecreatefromgif($filePath);
        } elseif (preg_match('/bmp/i', $ext)){
            $image = imagecreatefromwbmp($filePath);
        } else {
            return false;
        }
        
        imagejpeg($image, $newfileName . ".jpg", $quality);
        imagedestroy($image);
        $this->deleteFiles($filePath);
    }
    
    public function run()
    {          
        $sections = $this->getActiveCatalogSections();
        $elements = $this->getActiveCatalogElements($sections);
        $this->prepareDataToExport($elements["ITEMS"]);
        $this->saveImgs($elements["ITEMS"]);
        
        if ($this->packageUpload && !empty($this->packageCount)) {
            if (isset($elements["PERCENT"]) && $elements["PERCENT"] == 100) {
                $zip = $this->createZipArchive();
                if ($this->renderer) {
                    return $this->renderer->render(['path' => $zip]);
                }
                return null;
            }
            echo json_encode(array("lastId" => $elements["LAST_ID"], "percent" => $elements["PERCENT"]));
        } else {
            $zip = $this->createZipArchive();
            if ($this->renderer) {
                return $this->renderer->render(['path' => $zip]);
            }
            return null;
        }
    }
}