<?php
/**
 * Created by PhpStorm.
 * User: pnikonov
 * Date: 11.04.2018
 * Time: 15:21
 */

namespace Qsoft\Search\Levenshtein;


use Qsoft\Search\Levenshtein\ORM\DictionaryTable;

class Dictionary
{
    protected $words = [];

    public function addText($text)
    {
        if (strlen($text) > 0) {
            foreach ($this->parseText($text) as $word) {
                $this->addWord($word);
            }
        }

        return $this;
    }

    public function getWords()
    {
        return array_unique($this->words);
    }

    public static function filterWord($word, $full = true)
    {
        $word = trim($word, ",. \t\n\r\0\x0B");

        if (!$full) {
            return $word;
        }

        if (preg_match("/^[A-zА-Яа-я\.\-]+$/u", $word) && strlen(trim($word)) >= 3) {
            return $word;
        }
        return false;
    }

    protected function explodeWord($word)
    {
        $res = [$word];
        if (preg_match("/\./u", $word)) {
            $res[] = str_replace('.', '', $word);
        }
        if (strpos($word, "-")) {
            $addictional = explode("-", $word);
            $res = array_collapse([$res, $addictional]);
        }

        return $res;
    }

    public static function replaceDots($text)
    {
	return preg_replace("/(\w)(\.)((?!(\w){2,}))/u", "$1", $text);
    }

    protected function parseText($text)
    {
        return preg_split(
            '/((^\p{P}+)|\/|(\p{P}*\s+\p{P}*)|(\p{P}+$))/u',
            strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY
        );
    }

    protected function addWord($word)
    {
        if ($word = static::filterWord($word)) {
            $this->words = array_merge($this->words, $this->explodeWord($word));
        }

        return $this;
    }

    protected function excludeExisting()
    {
        $words = array_unique($this->words);

        $existingWords = [];

        $dbRes = DictionaryTable::getList(['select' => ['UF_WORD'],
            'filter' => ['=UF_WORD' => $words]
        ]);
        while ($dictItem = $dbRes->fetch()) {
            $existingWords[] = $dictItem['UF_WORD'];
        }

        $this->words = array_diff($words, $existingWords);

        return $this;
    }

    public function delete($write = false)
    {
        $words = array_unique($this->words);

        $existingWords = [];

        $dbRes = DictionaryTable::getList(['select' => ['ID', 'UF_WORD'],
            'filter' => ['=UF_WORD' => $words]
        ]);

        while ($dictItem = $dbRes->fetch()) {
            if ($write) {
                DictionaryTable::delete($dictItem['ID']);
            }
            $existingWords[] = $dictItem['UF_WORD'];
        }

        return $existingWords;
    }

    public function save()
    {
        $this->excludeExisting();

        $words = $this->prepareWords($this->words);

        $obDictionary = new DictionaryTable();

        foreach ($words as $word) {
            if (!$word['UF_METAPHONE']) {
                continue;
            }
            try{
                $result = $obDictionary->add($word);
                if (!$result->isSuccess()){

                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }

        }

        return $this;
    }

    public function prepareWord($word)
    {
        return [
            'UF_WORD' => $word,
            'UF_METAPHONE' => $this->mtphn($word),
            'UF_CUSTOM' => false,
            'UF_NOT_USE' => false,
        ];
    }

    public function prepareWords(array $words)
    {
        return array_map(function($word) {
            return $this->prepareWord($word);
        }, $words);
    }

    public static function mtphn($s)
    {
        if (!$s) {
            return '';
        }
        // определяем набор символов, которые нужно заменить
        $from = ['а', 'б', 'в', 'г', 'д', 'е', 'ё',  'ж',  'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц',  'ч',  'ш',  'щ',    'ъ', 'ы', 'ь', 'э', 'ю',  'я',  'á', 'ă', 'ắ', 'ặ', 'ằ', 'ẳ', 'ẵ', 'ǎ', 'â', 'ấ', 'ậ', 'ầ', 'ẩ', 'ẫ', 'ä', 'ǟ', 'ȧ', 'ǡ', 'ạ', 'ȁ', 'à', 'ả', 'ȃ', 'ā', 'ą', 'ᶏ', 'ẚ', 'å', 'ǻ', 'ḁ', 'ⱥ', 'ã', 'ɐ', 'ₐ', 'ḃ', 'ḅ', 'ɓ', 'ḇ', 'ᵬ', 'ᶀ', 'ƀ', 'ƃ', 'ć', 'č', 'ç', 'ḉ', 'ĉ', 'ɕ', 'ċ', 'ƈ', 'ȼ', 'ↄ', 'ꜿ', 'ď', 'ḑ', 'ḓ', 'ȡ', 'ḋ', 'ḍ', 'ɗ', 'ᶑ', 'ḏ', 'ᵭ', 'ᶁ', 'đ', 'ɖ', 'ƌ', 'ꝺ', 'é', 'ĕ', 'ě', 'ȩ', 'ḝ', 'ê', 'ế', 'ệ', 'ề', 'ể', 'ễ', 'ḙ', 'ë', 'ė', 'ẹ', 'ȅ', 'è', 'ẻ', 'ȇ', 'ē', 'ḗ', 'ḕ', 'ⱸ', 'ę', 'ᶒ', 'ɇ', 'ẽ', 'ḛ', 'ɛ', 'ᶓ', 'ɘ', 'ǝ', 'ₑ', 'ḟ', 'ƒ', 'ᵮ', 'ᶂ', 'ꝼ', 'ǵ', 'ğ', 'ǧ', 'ģ', 'ĝ', 'ġ', 'ɠ', 'ḡ', 'ᶃ', 'ǥ', 'ᵹ', 'ɡ', 'ᵷ', 'ḫ', 'ȟ', 'ḩ', 'ĥ', 'ⱨ', 'ḧ', 'ḣ', 'ḥ', 'ɦ', 'ẖ', 'ħ', 'ɥ', 'ʮ', 'ʯ', 'í', 'ĭ', 'ǐ', 'î', 'ï', 'ḯ', 'ị', 'ȉ', 'ì', 'ỉ', 'ȋ', 'ī', 'į', 'ᶖ', 'ɨ', 'ĩ', 'ḭ', 'ı', 'ᴉ', 'ᵢ', 'ǰ', 'ĵ', 'ʝ', 'ɉ', 'ȷ', 'ɟ', 'ʄ', 'ⱼ', 'ḱ', 'ǩ', 'ķ', 'ⱪ', 'ꝃ', 'ḳ', 'ƙ', 'ḵ', 'ᶄ', 'ꝁ', 'ꝅ', 'ʞ', 'ĺ', 'ƚ', 'ɬ', 'ľ', 'ļ', 'ḽ', 'ȴ', 'ḷ', 'ḹ', 'ⱡ', 'ꝉ', 'ḻ', 'ŀ', 'ɫ', 'ᶅ', 'ɭ', 'ł', 'ꞁ', 'ḿ', 'ṁ', 'ṃ', 'ɱ', 'ᵯ', 'ᶆ', 'ɯ', 'ɰ', 'ń', 'ň', 'ņ', 'ṋ', 'ȵ', 'ṅ', 'ṇ', 'ǹ', 'ɲ', 'ṉ', 'ƞ', 'ᵰ', 'ᶇ', 'ɳ', 'ñ', 'ó', 'ŏ', 'ǒ', 'ô', 'ố', 'ộ', 'ồ', 'ổ', 'ỗ', 'ö', 'ȫ', 'ȯ', 'ȱ', 'ọ', 'ő', 'ȍ', 'ò', 'ỏ', 'ơ', 'ớ', 'ợ', 'ờ', 'ở', 'ỡ', 'ȏ', 'ꝋ', 'ꝍ', 'ⱺ', 'ō', 'ṓ', 'ṑ', 'ǫ', 'ǭ', 'ø', 'ǿ', 'õ', 'ṍ', 'ṏ', 'ȭ', 'ɵ', 'ɔ', 'ᶗ', 'ᴑ', 'ᴓ', 'ₒ', 'ṕ', 'ṗ', 'ꝓ', 'ƥ', 'ᵱ', 'ᶈ', 'ꝕ', 'ᵽ', 'ꝑ', 'ʠ', 'ɋ', 'ꝙ', 'ꝗ', 'ŕ', 'ř', 'ŗ', 'ṙ', 'ṛ', 'ṝ', 'ȑ', 'ɾ', 'ᵳ', 'ȓ', 'ṟ', 'ɼ', 'ᵲ', 'ᶉ', 'ɍ', 'ɽ', 'ꞃ', 'ɿ', 'ɹ', 'ɻ', 'ɺ', 'ⱹ', 'ᵣ', 'ś', 'ṥ', 'š', 'ṧ', 'ş', 'ŝ', 'ș', 'ṡ', 'ṣ', 'ṩ', 'ʂ', 'ᵴ', 'ᶊ', 'ȿ', 'ꞅ', 'ſ', 'ẜ', 'ẛ', 'ẝ', 'ť', 'ţ', 'ṱ', 'ț', 'ȶ', 'ẗ', 'ⱦ', 'ṫ', 'ṭ', 'ƭ', 'ṯ', 'ᵵ', 'ƫ', 'ʈ', 'ŧ', 'ꞇ', 'ʇ', 'ú', 'ŭ', 'ǔ', 'û', 'ṷ', 'ü', 'ǘ', 'ǚ', 'ǜ', 'ǖ', 'ṳ', 'ụ', 'ű', 'ȕ', 'ù', 'ᴝ', 'ủ', 'ư', 'ứ', 'ự', 'ừ', 'ử', 'ữ', 'ȗ', 'ū', 'ṻ', 'ų', 'ᶙ', 'ů', 'ũ', 'ṹ', 'ṵ', 'ᵤ', 'ṿ', 'ⱴ', 'ꝟ', 'ʋ', 'ᶌ', 'ⱱ', 'ṽ', 'ʌ', 'ᵥ', 'ẃ', 'ŵ', 'ẅ', 'ẇ', 'ẉ', 'ẁ', 'ⱳ', 'ẘ', 'ʍ', 'ẍ', 'ẋ', 'ᶍ', 'ₓ', 'ý', 'ŷ', 'ÿ', 'ẏ', 'ỵ', 'ỳ', 'ƴ', 'ỷ', 'ỿ', 'ȳ', 'ẙ', 'ɏ', 'ỹ', 'ʎ', 'ź', 'ž', 'ẑ', 'ʑ', 'ⱬ', 'ż', 'ẓ', 'ȥ', 'ẕ', 'ᵶ', 'ᶎ', 'ʐ', 'ƶ', 'ɀ', 'ß' ];
        // определяем набор символов, на которые нужно заменить
        $to   = ['a', 'b', 'v', 'g', 'd', 'e', 'yo', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'ts', 'ch', 'sh', 'shch', '',  'y', '',  'e', 'yu', 'ya', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'b', 'b', 'b', 'b', 'b', 'b', 'b', 'b', 'c', 'c', 'c', 'c', 'c', 'c', 'c', 'c', 'c', 'c', 'c', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'd', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'f', 'f', 'f', 'f', 'f', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'g', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'h', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'j', 'j', 'j', 'j', 'j', 'j', 'j', 'j', 'k', 'k', 'k', 'k', 'k', 'k', 'k', 'k', 'k', 'k', 'k', 'k', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'l', 'm', 'm', 'm', 'm', 'm', 'm', 'm', 'm', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'q', 'q', 'q', 'q', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 's', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 't', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'v', 'v', 'v', 'v', 'v', 'w', 'w', 'w', 'w', 'w', 'w', 'w', 'w', 'w', 'x', 'x', 'x', 'x', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'ss'];
        // переводим в нижний регистр и делаем замены

        return metaphone( str_replace($from, $to, strtolower($s)) );
    }

    public static function getLang($word)
    {
        $arLanguages = ['ru', 'en'];

        $max_len = 0;
        $result = null;

        foreach ($arLanguages as $lang)
        {
            $ob = \CSearchLanguage::GetLanguage($lang);

            $arScanCodesTmp1 = $ob->ConvertToScancode($word, true);

            $arScanCodesTmp2_cnt = count(array_filter($arScanCodesTmp1));

            if ($arScanCodesTmp2_cnt > $max_len)
            {
                $max_len = $arScanCodesTmp2_cnt;
                $result = $lang;
            }
        }

        return $result;
    }

    public static function getSwitchLang($lang)
    {
        return array_get(['ru' => 'en', 'en' => 'ru'], $lang);
    }

    public static function conventLang($word, $lang)
    {
        if (!$lang) {
            return '';
        }
        return \CSearchLanguage::ConvertKeyboardLayout($word, $lang, static::getSwitchLang($lang));
    }

    public function clear()
    {
        unset($this->words);

        $this->words = [];

        return $this;
    }
}
