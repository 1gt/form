<?php
namespace Gt\Components;

use Bitrix\Iblock;
use Bitrix\Main;
use Bitrix\Main\Config;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Data;
use Bitrix\Main\Text;
use Bitrix\Main\Type;

class IblockForm extends \CBitrixComponent
{
    const ERROR_IBLOCK_MODULE_NOT_INSTALLED = 10001;
    const ERROR_FIELD_NOT_SET = 10002;
    const ERROR_IBLOCK_ELEMENT_ADD = 10003;

    /**
     * @var null|Main\Context
     */
    protected $context = null;

    /**
     * @var null|Data\Cache
     */
    protected $currentCache = null;

    /**
     * @var array
     */
    protected $errorsFatal = array();

    /**
     * @var array
     */
    protected $errorsNonFatal = array();

    /**
     * @inheritdoc
     */
    public function __construct($component)
    {
        parent::__construct($component);

        Loc::loadMessages(__FILE__);
        $this->context = Main\Application::getInstance()->getContext();
    }

    /**
     * Проверяет наличие необходимых модулей
     *
     * @throws Main\LoaderException
     * @throws Main\SystemException
     */
    protected function checkRequiredModules()
    {
        if (!Loader::includeModule('iblock')) {
            throw new Main\SystemException(
                Loc::getMessage('GTF_IBLOCK_MODULE_NOT_INSTALLED'),
                self::ERROR_IBLOCK_MODULE_NOT_INSTALLED
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function onPrepareComponentParams($params)
    {
        $params['IBLOCK_ID'] = (int)$params['IBLOCK_ID'];
        $params['FIELDS'] = (array)json_decode($params['FIELDS'], true);
        $params['FORM_VAR'] = $params['FORM_VAR'] ?: 'formKey';
        $params['UNIQUE_STRING'] = trim($params['UNIQUE_STRING']);
        $params['EVENT_TYPE'] = (string)$params['EVENT_TYPE'];
        $params['CACHE_TIME'] = (int)$params['CACHE_TIME'];
        if (!empty($params['CACHE_TYPE']) && $params['CACHE_TYPE'] === 'N') {
            $params['CACHE_TIME'] = 0;
        }

        return $params;
    }

    /**
     * Получение и обработка необходимых данных из БД
     *
     * @throws Main\SystemException
     * @throws \Exception
     */
    protected function obtainData()
    {
        $cacheId = array(
            'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
            'AVAIL_FIELDS' => $this->getAvailFields(),
            'AVAIL_PROPS' => $this->getAvailProperties(),
        );

        if ($this->startCache($cacheId)) {
            try {
                $this->arResult['FIELDS'] = array();

                $this->obtainDataFields();
                $this->obtainDataProperties();

                if (
                    Config\Option::get('main', 'component_managed_cache_on', 'Y') === 'Y'
                    && $this->arParams['CACHE_TYPE'] === 'A'
                    && $this->arParams['IBLOCK_ID'] > 0
                ) {
                    $taggedCache = Main\Application::getInstance()->getTaggedCache();
                    $taggedCache->registerTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
                }

                $this->endCache(array(
                    'FIELDS' => $this->arResult['FIELDS'],
                ));
            } catch (\Exception $e) {
                $this->abortCache();
                throw $e;
            }
        } else {
            $cachedData = $this->getCacheData();
            $this->arResult['FIELDS'] = $cachedData['FIELDS'];
        }

        foreach ($this->arParams['FIELDS'] as $field) {
            if (!empty($field['REQUIRED']) && isset($this->arResult['FIELDS'][$field['CODE']])) {
                $this->arResult['FIELDS'][$field['CODE']]['IS_REQUIRED'] = ($field['REQUIRED'] === 'Y' ? 'Y' : 'N');
            }
        }
    }

    /**
     * Получение параметров полей инфоблока
     *
     * @throws Main\ArgumentException
     */
    protected function obtainDataFields()
    {
        $availCodes = $this->getAvailFields();
        if (empty($availCodes)) {
            return;
        }
        $fieldList = Iblock\IblockFieldTable::getList(array(
            'select' => array('FIELD_ID', 'IS_REQUIRED', 'DEFAULT_VALUE'),
            'filter' => array(
                '=IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                '=FIELD_ID' => $availCodes,
            ),
        ));
        while ($field = $fieldList->fetch()) {
            $this->arResult['FIELDS'][$field['FIELD_ID']] = array(
                'CODE' => $field['FIELD_ID'],
                'NAME' => Loc::getMessage('GTF_IBLOCK_FIELD_' . $field['FIELD_ID']),
                'MULTIPLE' => 'N',
                'IS_REQUIRED' => $field['IS_REQUIRED'],
                'DEFAULT_VALUE' => $field['DEFAULT_VALUE'],
                'FIELD_NAME' => $this->getFieldName($field['FIELD_ID']),
            );
        }
    }

    /**
     * Получение параметров пользовательских свойств инфоблока
     *
     * @throws Main\ArgumentException
     */
    protected function obtainDataProperties()
    {
        $availCodes = $this->getAvailProperties();
        if (empty($availCodes)) {
            return;
        }
        $propertyList = Iblock\PropertyTable::getList(array(
            'select' => array(
                'CODE',
                'NAME',
                'MULTIPLE',
                'IS_REQUIRED',
                'DEFAULT_VALUE',
            ),
            'filter' => array(
                '=IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                '=ACTIVE' => 'Y',
                '=CODE' => $availCodes,
            ),
            'order' => array('SORT' => 'ASC', 'ID' => 'ASC'),
        ));
        while ($property = $propertyList->fetch()) {
            $key = 'PROPERTY_' . $property['CODE'];
            $property['FIELD_NAME'] = $this->getFieldName($key);
            $this->arResult['FIELDS'][$key] = $property;
        }
    }

    /**
     * Заполнение полей данными из запроса
     */
    protected function fillValuesFromRequest()
    {
        foreach ($this->arResult['FIELDS'] as &$field) {
            $field['VALUE'] = $field['DEFAULT_VALUE'];
            if (strlen($field['FIELD_NAME']) === 0) {
                continue;
            }
            if ($this->request->isPost() && $this->validateFormKey()) {
                $field['VALUE'] = $this->request->get($field['FIELD_NAME']);
            }
        }
        unset($field);
    }

    /**
     * Получение списка доступных полей инфоблока
     *
     * @return array
     */
    public function getAvailFields()
    {
        $result = array();
        foreach ($this->arParams['FIELDS'] as $field) {
            if (stripos($field['CODE'], 'PROPERTY_') === 0) {
                continue;
            }
            $result[] = $field['CODE'];
        }

        return $result;
    }

    /**
     * Получение списка доступных пользовательских свойств инфоблока
     *
     * @return array
     */
    public function getAvailProperties()
    {
        $result = array();
        foreach ($this->arParams['FIELDS'] as $field) {
            if (stripos($field['CODE'], 'PROPERTY_') !== 0) {
                continue;
            }
            $result[] = substr($field['CODE'], strlen('PROPERTY_'));
        }

        return $result;
    }

    /**
     * Формирование массива ошибок в $arResult
     */
    protected function formatResultErrors()
    {
        $errors = array();
        if (!empty($this->errorsFatal)) {
            $errors['FATAL'] = $this->errorsFatal;
        }
        if (!empty($this->errorsNonFatal)) {
            $errors['NONFATAL'] = $this->errorsNonFatal;
        }
        if (!empty($errors)) {
            $this->arResult['ERRORS'] = $errors;
        }
    }

    /**
     * Получение название поля для HTML элемента
     *
     * @param string $code Код поля
     * @return null|string
     */
    protected function getFieldName($code)
    {
        foreach ($this->arParams['FIELDS'] as $field) {
            if ($field['CODE'] !== $code) {
                continue;
            }
            return trim($field['FIELD_NAME']) ?: $field['CODE'];
        }

        return null;
    }

    /**
     * Получение уникального ключа (токена) формы
     *
     * @return string
     */
    public function getFormKey()
    {
        $fields = array(
            'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
            'TEMPLATE_NAME' => $this->getTemplateName(),
        );
        if (strlen($this->arParams['UNIQUE_STRING']) > 0) {
            $fields['UNIQUE_STRING'] = $this->arParams['UNIQUE_STRING'];
        }

        return md5(serialize($fields));
    }

    /**
     * Валидация ключа формы
     *
     * @return bool
     */
    protected function validateFormKey()
    {
        return ($this->request->get($this->arParams['FORM_VAR']) === $this->getFormKey());
    }

    /**
     * Получение уникального ключа для сессионных данных
     *
     * @return string
     */
    protected function getSessionKey()
    {
        return 'GT_IBLOCK_FORM_' . $this->getFormKey();
    }

    /**
     * Установка значение переменной сессии
     *
     * @param $key
     * @param $value
     */
    public function setSessionValue($key, $value)
    {
        $sessionKey = $this->getSessionKey();
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = array();
        }
        $_SESSION[$sessionKey][$key] = $value;
    }

    /**
     * Удаление сессионной переменной
     *
     * @param $key
     */
    public function unsetSessionValue($key)
    {
        $sessionKey = $this->getSessionKey();
        if (isset($_SESSION[$sessionKey][$key])) {
            unset($_SESSION[$sessionKey][$key]);
        }
    }

    /**
     * Получение значение сессионной переменной
     *
     * @param $key
     * @return null|mixed
     */
    public function getSessionValue($key)
    {
        $sessionKey = $this->getSessionKey();
        if (isset($_SESSION[$sessionKey][$key])) {
            return $_SESSION[$sessionKey][$key];
        }

        return null;
    }

    /**
     * Обработка формы
     */
    protected function processForm()
    {
        /** @global \CMain $APPLICATION */
        global $APPLICATION;

        if (!$this->request->isPost() || !$this->validateFormKey()) {
            return;
        }

        if ($this->validateForm()) {
            $values = array(
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => array(),
            );
            foreach ($this->arResult['FIELDS'] as $code => $field) {
                if (stripos($code, 'PROPERTY_') === 0) {
                    $values['PROPERTY_VALUES'][$field['CODE']] = $field['VALUE'];
                } else {
                    $values[$field['CODE']] = $field['VALUE'];
                }
            }

            $iblockElement = new \CIBlockElement();
            $elementId = $iblockElement->Add($values);
            if (intval($elementId) > 0) {
                if (!empty($this->arParams['EVENT_TYPE'])) {
                    \CEvent::Send($this->arParams['EVENT_TYPE'], SITE_ID, $this->getEventFields());
                }

                $this->setSessionValue('SUCCESS_ADDED', 'Y');
                if (!empty($this->arParams['REDIRECT_URI'])) {
                    LocalRedirect($this->arParams['REDIRECT_URI']);
                } else {
                    LocalRedirect($APPLICATION->GetCurPageParam(''));
                }
            } else {
                $this->addNonFatalError(
                    self::ERROR_IBLOCK_ELEMENT_ADD,
                    $iblockElement->LAST_ERROR
                );
            }
        }
    }

    /**
     * Валидация формы
     *
     * @return bool
     */
    protected function validateForm()
    {
        $isSuccess = true;
        foreach ($this->arResult['FIELDS'] as $code => $field) {
            $isFieldNotSet = false;
            if ($field['IS_REQUIRED'] !== 'Y') {
                continue;
            }
            $value = ($field['MULTIPLE'] === 'Y') ? (array)$field['VALUE'] : trim($field['VALUE']);
            if ($field['MULTIPLE'] === 'Y' && empty($value)) {
                $isFieldNotSet = true;
            } elseif ($field['MULTIPLE'] !== 'Y' && strlen($value) === 0) {
                $isFieldNotSet = true;
            }

            if ($isFieldNotSet) {
                $isSuccess = false;
                $this->addNonFatalError(
                    self::ERROR_FIELD_NOT_SET,
                    Loc::getMessage(
                        'GTF_FIELD_NOT_SET',
                        array('#FIELD_NAME#' => $field['NAME'])
                    ),
                    array('FIELD_CODE' => $code)
                );
            }
        }

        return $isSuccess;
    }

    /**
     * Получение значения полей для почтового события
     *
     * @return array
     */
    protected function getEventFields()
    {
        $data = array();
        foreach ($this->arResult['FIELDS'] as $code => $field) {
            $data[$code] = ($field['MULTIPLE'] === 'Y' ? implode(', ', $field['VALUE']) : $field['VALUE']);
        }

        return $data;
    }

    /**
     * Добавление критичной ошибки
     *
     * @param int $code Код ошибки
     * @param string $message Сообщение
     */
    protected function addFatalError($code, $message)
    {
        $this->errorsFatal[] = array(
            'CODE' => $code,
            'MESSAGE' => $message,
        );
    }

    /**
     * Добавление некритичной ошибки
     *
     * @param int $code Код ошибки
     * @param string $message Сообщение
     * @param mixed|null $additional Дополнительные данные
     */
    protected function addNonFatalError($code, $message, $additional = null)
    {
        $this->errorsNonFatal[] = array(
            'CODE' => $code,
            'MESSAGE' => $message,
            'ADDITIONAL_DATA' => $additional,
        );
    }

    /**
     * @inheritdoc
     */
    public function executeComponent()
    {
        try {
            $this->checkRequiredModules();
            $this->obtainData();
            $this->fillValuesFromRequest();
            $this->processForm();
        } catch (\Exception $e) {
            $this->addFatalError($e->getCode(), $e->getMessage());
        }

        if ($this->getSessionValue('SUCCESS_ADDED') === 'Y') {
            $this->arResult['SUCCESS_ADDED'] = 'Y';
            $this->unsetSessionValue('SUCCESS_ADDED');
        }
        $this->formatResultErrors();

        $this->includeComponentTemplate();
    }

    /**
     * @return bool
     * @throws Main\ArgumentNullException
     */
    final protected function getCacheNeed()
    {
        return ($this->arParams['CACHE_TIME'] > 0
            && $this->arParams['CACHE_TYPE'] !== 'N'
            && Config\Option::get('main', 'component_cache_on', 'Y') === 'Y');
    }

    /**
     * @param array $cacheId
     * @return bool
     */
    final protected function startCache($cacheId = array())
    {
        /** @global \CCacheManager $CACHE_MANAGER */
        global $CACHE_MANAGER;

        if (!$this->getCacheNeed()) {
            return true;
        }

        $this->currentCache = Data\Cache::createInstance();

        return $this->currentCache->startDataCache(
            $this->arParams['CACHE_TIME'],
            $this->getCacheKey($cacheId),
            $CACHE_MANAGER->GetCompCachePath($this->getRelativePath())
        );
    }

    /**
     * @param bool $data
     * @throws Main\SystemException
     */
    final protected function endCache($data = false)
    {
        if (!$this->getCacheNeed()) {
            return;
        }

        if ($this->currentCache === null) {
            throw new Main\SystemException('Кеширование не запущено');
        }

        $this->currentCache->endDataCache($data);
        $this->currentCache = null;
    }

    /**
     * @throws Main\SystemException
     */
    final protected function abortCache()
    {
        if (!$this->getCacheNeed()) {
            return;
        }

        if ($this->currentCache === null) {
            throw new Main\SystemException('Кеширование не запущено');
        }

        $this->currentCache->abortDataCache();
        $this->currentCache = null;
    }

    /**
     * @return null
     * @throws Main\SystemException
     */
    final protected function getCacheData()
    {
        if (!$this->getCacheNeed()) {
            return null;
        }

        if ($this->currentCache === null) {
            throw new Main\SystemException('Кеширование не запущено');
        }

        return $this->currentCache->getVars();
    }

    /**
     * @param array $cacheId
     * @return string
     */
    final protected function getCacheKey($cacheId = array())
    {
        if (!is_array($cacheId)) {
            $cacheId = array((string)$cacheId);
        }

        $cacheId['SITE_ID'] = $this->getSiteId();
        $cacheId['LANGUAGE_ID'] = $this->getLanguageId();
        $cacheId['CACHE_TIME'] = $this->arParams['CACHE_TIME'];
        $cacheId['SITE_TEMPLATE_ID'] = $this->getSiteTemplateId();

        return implode('|', $cacheId);
    }
}
