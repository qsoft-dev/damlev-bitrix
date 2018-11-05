<?php
/**
 * Created by PhpStorm.
 * User: pnikonov
 * Date: 11.04.2018
 * Time: 17:32
 */

namespace Qsoft\Search\Levenshtein;


use Bitrix\Main\Entity\ExpressionField;
use Oefenweb\DamerauLevenshtein\DamerauLevenshtein;
use Qsoft\Search\Levenshtein\ORM\DictionaryTable;

class Search
{
    protected $dict = [];
    protected $metaphone = [];
    protected $debugOn = false;
    protected $minSimilarPercent = 60;

    public function loadData()
    {
        if (!empty($dict)) {
            return $this;
        }

        $dbRes = DictionaryTable::getList();
        while ($item = $dbRes->fetch()) {
            $this->dict[$item['ID']] = $item['UF_WORD'];
            $this->metaphone[$item['ID']] = $item['UF_METAPHONE'];
        }
        return $this;
    }

    public function debug($on = false)
    {
        $this->debugOn = $on;

        return $this;
    }

    public function setMinPercent($val)
    {
        $this->minSimilarPercent = (float)$val;

        return $this;
    }

    public function checkWord($word)
    {
        $result = [];
        $word_metaphone = Dictionary::mtphn($word);

        if (!$word_metaphone) {
            return false;
        }

        $this->loadData();

        foreach ($this->metaphone as $id => $metaphone) {
            if (levenshtein($word_metaphone, $metaphone) <= 1) {
                $result[] = $this->dict[$id];
            }
        }

        return $result;
    }

    public function findWords($word)
    {
        $word = strtolower($word);
        
        $arWords = array_filter(explode(' ', $word), function($item) {
            return trim($item);
        });

        if (count($arWords) > 3) {
            return [];
        }

        if (count($arWords) == 1) {
            if (preg_match('/\d/', $word))
                return $word;
            return $this->findWord($word);
        }
        $result = [];

        foreach ($arWords as $wordItem) {
            if (preg_match('/\d/', $wordItem)) {
                $result[] = $wordItem;
                continue;
            }

            $variants = $this->findWord($wordItem);

            if (empty($variants)) {
                $variants = [$wordItem];
            }
            $result[] = array_first($variants);
        }

        if (count($result) == 0) {
            return [];
        }

        return [implode(' ', $result)];
    }

    public function findWord($word)
    {
        global $DB;

        if (strlen($word) < 3 || !Dictionary::filterWord($word, false)) {
            return [$word];
        }

        $maxDistance = 2;
        $result = [];
        $similar_text_result = [];

        $word_metaphone = Dictionary::mtphn($word);

        $word_convent = Dictionary::conventLang($word, Dictionary::getLang($word));

        if (!$word_metaphone && !$word_convent) {
            return [$word];
        }

        $word_convent_metaphone =  Dictionary::mtphn($word_convent);

        if ($this->debugOn) {
            dump([
                $word => $word_metaphone,
                $word_convent => $word_convent_metaphone,
            ]);
        }

        $dbRes = DictionaryTable::getList([
            'filter' => [
                '=UF_NOT_USE' => false,
                '<DAMLEVLIM_ALL' => $maxDistance,
            ],
            'order' => [
                'DAMLEVLIM' => 'asc',
            ],
            'runtime' => array(
                new ExpressionField('DAMLEVLIM', 'DAMLEVLIM(\''.$DB->ForSql($word_metaphone).'\', UF_METAPHONE, 20)'),
                new ExpressionField('DAMLEVLIM_CONVENT', 'DAMLEVLIM(\''.$DB->ForSql($word_convent_metaphone).'\', UF_METAPHONE, 20)'),
                new ExpressionField('DAMLEVLIM_ALL', 'LEAST(%s, %s)', ['DAMLEVLIM', 'DAMLEVLIM_CONVENT']),
            ),
            'select' => [
                'ID', 'UF_WORD', 'UF_METAPHONE',
                'DAMLEVLIM_ALL',
                'DAMLEVLIM',
                'DAMLEVLIM_CONVENT',
            ],
            'cache' => [
                'ttl' => 3600 * 24 * 7
            ]
        ]);

        while ($item = $dbRes->fetch()) {

            $test_word = (int)$item['DAMLEVLIM_CONVENT'] < (int)$item['DAMLEVLIM'] ? $word_convent : $word;

            if ($test_word == $item['UF_WORD']) {
                return [$item['UF_WORD']];
            }

            $phpDamLevDistance = (new DamerauLevenshtein($test_word, $item['UF_WORD']))->getSimilarity();

            if ($phpDamLevDistance > $maxDistance) {
                continue;
            }

            $item['PHP_DAMLEV_DISTANCE'] = $phpDamLevDistance;

            $test_word_cp1251 = mb_convert_encoding($test_word, 'CP1251');
            $dict_word_cp1251 = mb_convert_encoding($item['UF_WORD'], 'CP1251');

            $item['SIM_MATCH'] = similar_text(
                $test_word_cp1251,
                $dict_word_cp1251,
                $item['SIM']
            );

            $item['SIM_R_MISPRINT'] = static::checkLastLetterMisprint($test_word, $item['UF_WORD']);

            if ($this->minSimilarPercent <= ($sim = max($item['SIM'], $item['SIM_R_MISPRINT']))) {
                $result[] = $item;
                $similar_text_result[] = $sim;
            }
        }
        if (empty($result)) {
            return [];
        }
        $max_similarity = max($similar_text_result);

        if ($this->debugOn) {
            dump([
                '$max_similarity' => $max_similarity,
                '$result_b' => $result,
            ]);
        }

        $most_similar_strings = array_flip(array_keys($similar_text_result, $max_similarity));

        $result = array_intersect_key($result, $most_similar_strings);

        if ($this->debugOn) {
            dump(['$result' => $result]);
        }

        return array_pluck($result, 'UF_WORD');
    }

    public static function checkLastLetterMisprint($word1, $word2)
    {
        return ($word2 === substr($word1, 0, strlen($word1) - 1)) ? 100 : 0;
    }

    public function getData($query, $variants = [])
    {
        $was_edited = false;
        $correctQuery = $query;
        if (count($variants) > 0) {
            $correctQuery = array_shift($variants);
            if ($correctQuery && $correctQuery !== $query) {
                $was_edited = true;
                array_unshift($variants, $query);
            }
        }

        return [
            'q' => $correctQuery,
            'was_edited' => $was_edited,
            'variants' => $variants,
        ];

        return false;
    }
}