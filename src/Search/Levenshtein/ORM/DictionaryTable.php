<?php namespace Qsoft\Search\Levenshtein\ORM;

use Bitrix\Main,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
/**
 * Class DictionaryTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> UF_WORD string optional
 * <li> UF_METAPHONE string optional
 * </ul>
 *
 * @package Bitrix\Dictionary
 **/

class DictionaryTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'q_search_dictionary';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return array(
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
                'title' => Loc::getMessage('DICTIONARY_ENTITY_ID_FIELD'),
            ),
            'UF_WORD' => array(
                'data_type' => 'text',
                'title' => Loc::getMessage('DICTIONARY_ENTITY_UF_WORD_FIELD'),
            ),
            'UF_METAPHONE' => array(
                'data_type' => 'text',
                'title' => Loc::getMessage('DICTIONARY_ENTITY_UF_METAPHONE_FIELD'),
            ),
            new Main\Entity\BooleanField('UF_CUSTOM'),
            new Main\Entity\BooleanField('UF_NOT_USE'),
        );
    }
}