<?
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @var CBitrixComponentTemplate $this */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$this->setFrameMode(true);

/** @var \Gt\Components\IblockForm $component */
$component = $this->__component;
$cv = \Bitrix\Main\Text\Converter::getHtmlConverter();
?>

<? $frame = $this->createFrame()->begin(''); ?>
    <div class="iblock-form">
        <? if (isset($arResult['SUCCESS_ADDED']) && $arResult['SUCCESS_ADDED'] === 'Y') : ?>
            <? ShowMessage(array('MESSAGE' => 'ОК', 'TYPE' => 'OK')); ?>
        <? endif; ?>
        <? if (!empty($arResult['ERRORS']['FATAL'])) : ?>
            <? foreach ($arResult['ERRORS']['FATAL'] as $error) : ?>
                <? ShowError($error['MESSAGE']); ?>
            <? endforeach; ?>
        <? endif; ?>
        <? if (!empty($arResult['ERRORS']['NONFATAL'])) : ?>
            <? foreach ($arResult['ERRORS']['NONFATAL'] as $error) : ?>
                <? ShowError($error['MESSAGE']); ?>
            <? endforeach; ?>
        <? endif; ?>

        <form action="<?= POST_FORM_ACTION_URI; ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?= $cv->encode($arParams['FORM_VAR']); ?>"
                   value="<?= $component->getFormKey(); ?>">

            <? if (($field = $arResult['FIELDS']['NAME'])) : ?>
                <input type="text" name="<?= $cv->encode($field['FIELD_NAME']); ?>"
                       value="<?= $cv->encode($field['VALUE']); ?>">
            <? endif; ?>
            <? if (($field = $arResult['FIELDS']['PROPERTY_EMAIL'])) : ?>
                <input type="text" name="<?= $cv->encode($field['FIELD_NAME']); ?>"
                       value="<?= $cv->encode($field['VALUE']); ?>">
            <? endif; ?>
            <? if (($field = $arResult['FIELDS']['PROPERTY_PHONE'])) : ?>
                <input type="text" name="<?= $cv->encode($field['FIELD_NAME']); ?>"
                       value="<?= $cv->encode($field['VALUE']); ?>">
            <? endif; ?>
            <button type="submit">Отправить</button>
        </form>
    </div>
<? $frame->end(); ?>