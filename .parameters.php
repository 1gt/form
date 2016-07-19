<?php

/** @var array $arCurrentValues */

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Internal;
use Bitrix\Iblock;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

if (!Main\Loader::includeModule('iblock')) {
    return;
}

$relativePath = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']));

$iblockTypes = array();
$iblockTypeList = Iblock\TypeTable::getList(array(
    'select' => array('ID', 'NAME' => 'LANG_MESSAGE.NAME'),
    'filter' => array('=LANG_MESSAGE.LANGUAGE_ID' => LANGUAGE_ID),
    'order' => array('LANG_MESSAGE.NAME' => 'ASC'),
));
while ($iblockType = $iblockTypeList->fetch()) {
    $iblockTypes[$iblockType['ID']] = sprintf('[%s] %s', $iblockType['ID'], $iblockType['NAME']);
}
unset($iblockType, $iblockTypeList);

$iblocks = array();
$iblockFilter = array('=ACTIVE' => 'Y');
if (!empty($arCurrentValues['IBLOCK_TYPE'])) {
    $iblockFilter['=IBLOCK_TYPE_ID'] = (string)$arCurrentValues['IBLOCK_TYPE'];
}
$iblockList = Iblock\IblockTable::getList(array(
    'select' => array('ID', 'NAME'),
    'filter' => $iblockFilter,
    'order' => array('NAME' => 'ASC'),
));
while ($iblock = $iblockList->fetch()) {
    $iblocks[$iblock['ID']] = sprintf('[%d] %s', $iblock['ID'], $iblock['NAME']);
}
unset($iblock, $iblockList);

$availFields = array(
    array('CODE' => 'NAME', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_NAME')),
    array('CODE' => 'CODE', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_CODE')),
    array('CODE' => 'XML_ID', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_XML_ID')),
    array('CODE' => 'ACTIVE_FROM', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_ACTIVE_FROM')),
    array('CODE' => 'ACTIVE_TO', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_ACTIVE_TO')),
    array('CODE' => 'PREVIEW_TEXT', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_PREVIEW_TEXT')),
    array('CODE' => 'DETAIL_TEXT', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_DETAIL_TEXT')),
    array('CODE' => 'PREVIEW_PICTURE', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_PREVIEW_PICTURE')),
    array('CODE' => 'DETAIL_PICTURE', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_DETAIL_PICTURE')),
    array('CODE' => 'IBLOCK_SECTION_ID', 'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_IBLOCK_SECTION_ID')),
);
if (!empty($arCurrentValues['IBLOCK_ID'])) {
    $propertyList = Iblock\PropertyTable::getList(array(
        'select' => array('CODE', 'NAME'),
        'filter' => array(
            '=IBLOCK_ID' => (int)$arCurrentValues['IBLOCK_ID'],
            '=ACTIVE' => 'Y',
        ),
        'order' => array('NAME' => 'ASC')
    ));
    while ($property = $propertyList->fetch()) {
        if (strlen(trim($property['CODE'])) === 0) {
            continue;
        }
        $availFields[] = array(
            'CODE' => 'PROPERTY_' . $property['CODE'],
            'NAME' => sprintf('[%s] %s', $property['CODE'], $property['NAME']),
        );
    }
    unset($property, $propertyList);
}

$eventTypes = array();
$eventTypeList = Internal\EventTypeTable::getList(array(
    'select' => array('EVENT_NAME', 'NAME'),
    'filter' => array('=LID' => LANGUAGE_ID),
    'order' => array('EVENT_NAME' => 'ASC'),
));
while ($eventType = $eventTypeList->fetch()) {
    $eventTypes[$eventType['EVENT_NAME']] = sprintf('[%s] %s', $eventType['EVENT_NAME'], $eventType['NAME']);
}
unset($eventType, $eventTypeList);

$arComponentParameters = array(
    'GROUPS' => array(),
    'PARAMETERS' => array(
        'IBLOCK_TYPE' => array(
            'PARENT' => 'DATA_SOURCE',
            'NAME' => Loc::getMessage('GTF_PARAM_IBLOCK_TYPE'),
            'TYPE' => 'LIST',
            'ADDITIONAL_VALUES' => 'Y',
            'VALUES' => $iblockTypes,
            'REFRESH' => 'Y',
        ),
        'IBLOCK_ID' => array(
            'PARENT' => 'DATA_SOURCE',
            'NAME' => Loc::getMessage('GTF_PARAM_IBLOCK_ID'),
            'TYPE' => 'LIST',
            'ADDITIONAL_VALUES' => 'Y',
            'VALUES' => $iblocks,
            'REFRESH' => 'Y',
        ),
        'FIELDS' => array(
            'PARENT' => 'DATA_SOURCE',
            'NAME' => Loc::getMessage('GTF_PARAM_FIELDS'),
            'TYPE' => 'CUSTOM',
            'JS_FILE' => $relativePath . '/settings/settings.js?t=' . time(),
            'JS_EVENT' => 'gtIblockFormFieldsController',
            'JS_DATA' => json_encode(array(
                'availFields' => $availFields,
                'additionalCss' => $relativePath . '/settings/settings.css?t=' . time(),
            )),
            'DEFAULT' => '[]',
        ),
        'FORM_VAR' => array(
            'PARENT' => 'DATA_SOURCE',
            'NAME' => Loc::getMessage('GTF_PARAM_FORM_VAR'),
            'TYPE' => 'STRING',
            'DEFAULT' => 'formKey',
        ),
        'UNIQUE_STRING' => array(
            'PARENT' => 'ADDITIONAL_SETTINGS',
            'NAME' => Loc::getMessage('GTF_PARAM_UNIQUE_STRING'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ),
        'EVENT_TYPE' => array(
            'PARENT' => 'ADDITIONAL_SETTINGS',
            'NAME' => Loc::getMessage('GTF_PARAM_EVENT_TYPE'),
            'TYPE' => 'LIST',
            'ADDITIONAL_VALUES' => 'Y',
            'VALUES' => $eventTypes,
        ),
        'CACHE_TIME' => array('DEFAULT' => 3600),
    ),
);
