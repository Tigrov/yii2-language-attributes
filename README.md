# yii2-language-attributes
Behavior for language attributes for Yii2

Behavior LanguageAttributes is adding possibility to localize ActiveRecord model.
It is finding a model field for application language.

E.g. we have table with language fields: `name_en`, `name_ru`, `name_zh_cn` where we store values for each language (en, ru, zh-CN).

When we try to get object field `$model->name;`,
if current language of an application is `en-US`
the behavior will try to find field `name_en_us` then `name_en`.
If current language is `de` and source language is `en`
the behavior will try to find field `name_de` then `name` and after the field for source language `name_en`.

When we try to get object field `$model->nameList;`,
it will try to get all `name` values for current language.
`$model->nameList;` returns array of values.

Using
-----

By default attribute list consist of an attribute `name`
```php
/**
 * @property string $name_en
 * @property string $name_ru
 * @property string $name_zh_cn
 */
class Model extends ActiveRecord {
    ...
    public function behaviors()
    {
        return [
            'languageAttribute' => LanguageAttributes::class,
        ];
    }
    ...
}

// this will try to find a value for the current language of an application.
$model->name;
```

Extended example
```php
/**
 * @property string $name_en
 * @property string $name_ru
 * @property string $name_zh_cn
 * @property string $field_en
 * @property string $field_ru
 * @property string $field_zh_cn
 * @property string $status
 */
class Model extends ActiveRecord {
    ...
    public function behaviors()
    {
        return [
            'languageAttribute' => [
                'class' => LanguageAttributes::class,
                'attributes' => ['name', 'field'],
                'languages' => ['en', 'ru', 'zh_cn'],
                'query' => ['where' => ['status' => 'active']],
                'sort' => true,
            ]
        ];
    }
    ...
}

// Try to find values for the current language of an application.
$model->name;
$model->field;

// Get list of all values from a model table for the current language
$model->nameList;
$model->fieldList;

// Copy all language values from $model2->name to $model->name
$model->copyValue($model2, 'name');

// Copy all language values from $model2->field2 to $model->field
$model->copyValue($model2, 'field2', 'field');
```