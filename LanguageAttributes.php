<?php
namespace tigrov;

use yii\db\ActiveRecord;
use yii\base\Behavior;
use yii\db\Expression;
use Yii;

/**
 * Behavior LanguageAttributes is adding possibility to localize ActiveRecord model.
 * It is finding a model field for application language.
 *
 * E.g. we have table with language fields: `field_en`, `field_ru`, `field_zh_cn` where we store values for each language (en, ru, zh-CN).
 *
 * When we try to get object field `$model->field;`,
 * if current language of an application is `en-US`
 * the behavior will try to find field `field_en_us` then `field_en`.
 * If current language is `de` and source language is `en`
 * the behavior will try to find field `field_de` then `field` and after the field for source language `field_en`.
 *
 * When we try to get object field `$model->fieldList;`,
 * it will try to get all `field` values for current language.
 * `$model->fieldList;` returns array of values.
 */
class LanguageAttributes extends Behavior
{
    /**
     * @var string[] list of attributes that are to be automatically detected value for each language.
     */
    public $attributes = ['name'];

    /**
     * @var array additional conditions, joins and etc.
     * Using for \Yii::configure($activeQuery, $query)
     */
    public $query = [];

    /**
     * @var bool indicated to sort result or not.
     */
    public $sort = true;

    /**
     * @var string[] available languages for the attributes, uses to copy values for each language, see `copyValue()`
     */
    public $languages;

    public function init()
    {
        if ($this->languages === null) {
            $languages = [Yii::$app->language, Yii::$app->sourceLanguage];
            if (!empty(Yii::$app->params['languages']) && is_array(Yii::$app->params['languages'])) {
                $languages = array_merge($languages, Yii::$app->params['languages']);
            }

            $this->languages = array_unique($languages);
        }

        $this->languages = array_map('strtolower', $this->languages);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->attributes) || parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (in_array($name, $this->attributes)) {
            return $this->getValue($name);
        } elseif (substr_compare($name, 'list', -4, 4, true) === 0 && in_array(substr($name, 0, -4), $this->attributes)) {
            return static::values($this->owner, substr($name, 0, -4), $this->query, $this->sort);
        }

        return parent::__get($name);
    }

    /**
     * Get the value for specified attribute.
     *
     * @param $attribute
     * @return mixed|string
     */
    protected function getValue($attribute)
    {
        $fields = static::fields($this->owner, $attribute);

        foreach ($fields as $field) {
            if ($this->owner->$field) {
                return $this->owner->$field;
            }
        }

        return '';
    }

    /**
     * Get fields for specified attribute.
     *
     * @param string|ActiveRecord $arClass ActiveRecord model or class name
     * @param string $attribute
     * @return string[]
     */
    protected static function fields($arClass, $attribute)
    {
        $sourceFields = static::sourceFields($attribute);
        $fields = array_intersect($sourceFields, array_keys($arClass::getTableSchema()->columns));
        if (!$fields) {
            throw new \yii\base\InvalidCallException('Language fields not found for attribute "' . $attribute . '".');
        }

        return $fields;
    }

    /**
     * Prepare list of fields for specified attribute.
     *
     * @param string $attribute
     * @return string[]
     */
    protected static function sourceFields($attribute)
    {
        $fields = static::languageFields($attribute, Yii::$app->language);
        if (Yii::$app->language != Yii::$app->sourceLanguage) {
            $fields = array_merge($fields, static::languageFields($attribute, Yii::$app->sourceLanguage));
        }
        $fields[] = $attribute;

        return $fields;
    }

    /**
     * Prepare list of fields for specified language.
     *
     * @param string $attribute
     * @param string $language
     * @return string[]
     */
    protected static function languageFields($attribute, $language)
    {
        $list = [];
        for (
            $parts = explode('-', str_replace('_', '-', strtolower($language)));
            count($parts);
            array_pop($parts)
        ) {
            $list[] = $attribute . '_' . implode('_', $parts);
        }

        return $list;
    }

    /**
     * List of values from the table for an attribute
     *
     * @param string|ActiveRecord $arClass ActiveRecord model or class name
     * @param string $attribute
     * @param array $query additional conditions, joins and etc.
     * @param bool $sort indicated to sort result or not.
     * @return array
     */
    public static function values($arClass, $attribute, $query = [], $sort = true)
    {
        static $list = [];
        $className = $arClass::className();
        $language = Yii::$app->language;
        if ($query || !isset($list[$className][$attribute][$language])) {
            $pkeys = $arClass::primaryKey();
            $pkey = count($pkeys) == 1 ? $pkeys[0] : null;

            $fields = static::fields($arClass, $attribute);
            $fields = array_map([$arClass::getDb(), 'quoteColumnName'], $fields);
            $select = [new Expression('COALESCE(' . implode(', ', $fields) . ') AS name')];
            if ($pkey) {
                $select[] = $pkey;
            }

            /* @var \yii\db\ActiveQuery $activeQuery */
            $activeQuery = $arClass::find()->select($select);

            if ($query) {
                Yii::configure($activeQuery, $query);
            }
            if ($sort) {
                $activeQuery->orderBy(['name' => SORT_ASC]);
            }
            if ($pkey) {
                $activeQuery->indexBy($pkey);
            }
            $result = $activeQuery->column();

            if ($query) {
                return $result;
            } else {
                $list[$className][$attribute][$language] = $result;
            }
        }

        return $list[$className][$attribute][$language];
    }

    /**
     * Copy value for each language.
     *
     * @param ActiveRecord $object from which value will be copied
     * @param string $objectAttribute from which value will be copied
     * @param string $attribute to which value will be copied
     */
    public function copyValue($object, $objectAttribute, $attribute = null)
    {
        $attribute = $attribute ?: $objectAttribute;

        /**
         * @var ActiveRecord $owner
         */
        $owner = $this->owner;
        $columns = $owner::getTableSchema()->columns;
        if (isset($columns[$attribute])) {
            $owner->$attribute = $object->$objectAttribute;
        }

        $appLanguage = Yii::$app->language;
        foreach ($this->languages as $language) {
            foreach (static::languageFields($attribute, $language) as $field) {
                if (isset($columns[$field])) {
                    Yii::$app->language = $language;
                    $owner->$field = $object->$objectAttribute;
                }
            }
        }

        Yii::$app->language = $appLanguage;
    }
}