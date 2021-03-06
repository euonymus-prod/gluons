<?php
namespace App\Utils;

/**
 * Utility : 
 * 
 * @category Awesomeness
 * @package  Utility
 * @author   euonymus
 * @license  euonymus
 * @version  1.0.0
 */

use Cake\Cache\Cache;
use Cake\Cache\Engine\FileEngine;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

use App\Model\Table\QuarkTypesTable;

class Wikipedia
{
  public function __construct(array $token = array(), array $consumer = array())
  {
  }

  const CONTENT_TYPE_NONE         = 'none';
  const CONTENT_TYPE_PERSON       = 'person';
  const CONTENT_TYPE_MOVIE        = 'movie';
  const CONTENT_TYPE_ORGANIZATION = 'organization';
  const CONTENT_TYPE_MUSIC        = 'music';
  const CONTENT_TYPE_CHARACTER    = 'character';
  const CONTENT_TYPE_BOOK         = 'book';
  const CONTENT_TYPE_MANGA        = 'manga';

  public static $contentType = self::CONTENT_TYPE_PERSON;

  public static $xpath_main = '//div[contains(@class, "mw-parser-output")]';
  public static $xpath_infobox = '//table[contains(@class,"infobox")]';
  public static $xpath_infobox_country = '//table[contains(@class,"infoboxCountry")]';
  public static $xpath_redirect = '//*[contains(@class, "redirectMsg")]';
  public static $xpath_disambig = '//*[contains(@id, "disambigbox")]';

  public static $retrieveCacheConfig = 'default';

  public static function readPage($query, $infobox = false)
  {
    if (!is_string($query) || empty($query)) return false;
    $template = '/wiki/%s';
    $path = sprintf($template, urlencode($query));
    return self::readPageByPath($path, $infobox);
  }
  public static function readPageByPath($path, $infobox = false)
  {
    if (!is_string($path) || empty($path)) return false;
    $template = 'https://ja.wikipedia.org%s';
    $url = sprintf($template, $path);
    return self::readPageByUrl($url, $infobox);
  }
  public static function readPageByUrl($url, $infobox = false)
  {
    if ($infobox) {
      $element = self::$xpath_infobox;
    } else {
      $element = self::$xpath_main;
    }
    U::$retrieveCacheConfig = self::$retrieveCacheConfig;
    $res = U::getXpathFromUrl($url, $element);
    if (!$res) return false;

    return $res[0];
  }


  public static function parseInfoBox($xml)
  {
    if (!is_object($xml)) return false;
    $element = @$xml->xpath(self::$xpath_infobox);
    if (!$element || !is_array($element) || !property_exists($element[0], 'tr')) return false;
    return $element[0]->tr;
  }

  public static function parseInfoBoxCountry($xml)
  {
    if (!is_object($xml)) return false;
    $element = @$xml->xpath(self::$xpath_infobox_country);
    if (!$element || !is_array($element) || !property_exists($element[0], 'tr')) return false;
    return $element[0]->tr;
  }
  /*********************************************************************************/
  /* quark                                                                         */
  /*********************************************************************************/
  public static $internal = false;
/*
  public static function readPageForQuark($query)
  {
    $xml = self::readPage($query);
    if (!$xml) return false;

    $ret = self::constructData($xml);
    if (!$ret || !is_array($ret)) return false;

    // get google image
    if (!self::$internal &&
	(!array_key_exists('image_path', $ret) || empty($ret['image_path']))
    ) {
      $res =  GoogleSearch::getFirstImageFromImageSearch($query);
      if ($res && (strlen($res) <= 255)) {
	$ret['image_path'] =  $res;
      }
    }

    $xpath = '//h1[contains(@id,"firstHeading")]';
    $element = @$xml->xpath($xpath);
    if (!$element || !is_array($element)) {
      $ret['name'] = $query;
    } else {
      $ret['name'] = (string)$element[0];
    }

    return $ret;
  }
*/

  public static function readPageForQuark($query)
  {
    $xml = self::readPage($query);
    if (!$xml) return false;
    return self::readPageByXmlForQuark($query, $xml);
  }
  public static function readPageByXmlForQuark($query, $xml, $wid = null)
  {
    $ret = self::constructData($xml);
    if (!$ret || !is_array($ret)) return false;

    if (!array_key_exists('name', $ret)) {
      $ret['name'] = $query;
    }
    $ret['wid'] = $wid;

    // get google image
    if (!self::$internal &&
	(!array_key_exists('image_path', $ret) || empty($ret['image_path']))
    ) {
      $res =  GoogleSearch::getFirstImageFromImageSearch($query);
      if ($res && (strlen($res) <= 255)) {
	$ret['image_path'] =  $res;
      }
    }
    return $ret;
  }

  public static function constructData($xml)
  {
    // get name
    $xpath = '//h1[contains(@id,"firstHeading")]';
    $element = @$xml->xpath($xpath);
    $name = false;
    if ($element && is_array($element)) {
      $name = (string)$element[0];
    }

    // get quark_type
    $quark_type_id = ($res = self::retrieveQtype($xml, $name)) ? $res : '';

    // get description
    $description = ($res = self::retrieveDescription($xml)) ? $res : '';

    // get image_path
    //$image_path = ($res = self::retrieveImagePath($xml)) ? $res : NULL;
    $image_path = NULL;
    if ($quark_type_id) {
      $QuarkTypes = TableRegistry::get('QuarkTypes');
      $image_path = $QuarkTypes->getImagePath($quark_type_id);
    }
    $res = self::retrieveImagePath($xml);
    if ($res && (strlen($res) <= 255)) $image_path = $res;
    
    // get start array
    $start = self::retrieveStart($xml);
    $dateArr = U::normalizeDateArrayFormat($start);
    if ($dateArr) {
      $start          = $dateArr['date'];
      $start_accuracy = $dateArr['date_accuracy'];
    } else {
      $start          = NULL;
      $start_accuracy = '';
    }

    // get end array
    $end = self::retrieveEnd($xml);
    $dateArr = U::normalizeDateArrayFormat($end);
    if ($dateArr) {
      $end          = $dateArr['date'];
      $end_accuracy = $dateArr['date_accuracy'];
    } else {
      $end          = NULL;
      $end_accuracy = '';
    }

    $is_momentary = false;

    // get url
    $url = ($res = self::retrieveUrl($xml)) ? $res : '';

    // is_person
    if (self::$contentType == self::CONTENT_TYPE_PERSON) {
      $is_person = true;
    } else {
      $is_person = false;
    }

    $ret = [
	    'image_path'            => $image_path,
	    'description'           => $description,
	    'start'                 => $start,
	    'start_accuracy'        => $start_accuracy,
	    'end'                   => $end,
	    'end_accuracy'          => $end_accuracy,
	    'is_momentary'          => $is_momentary,
	    'url'                   => $url,
	    'is_person'             => $is_person,
	    'wikipedia_sourced'     => 1,
	    'quark_type_id'         => $quark_type_id,
	    ];
    if ($name) $ret['name'] = $name;
    return $ret;
  }

  public static function retrieveQtype($xml, $name = false)
  {
    // check the name first
    if (preg_match('/大学$/', $name)) return QuarkTypesTable::TYPE_UNIVERSITY;
    if (preg_match('/(小学校|初等部)$/', $name)) return QuarkTypesTable::TYPE_ELEM_SCHOOL;
    if (preg_match('/中(学校|等部)$/', $name)) return QuarkTypesTable::TYPE_MID_SCHOOL;
    if (preg_match('/高((等学)?校|等部)$/', $name)) return QuarkTypesTable::TYPE_HIGH_SCHOOL;
    if (preg_match('/(幼稚|保育)園$/', $name)) return QuarkTypesTable::TYPE_PRE_SCHOOL;
    if (preg_match('/(塾|予備校)$/', $name)) return QuarkTypesTable::TYPE_SCHOOL;
    if (preg_match('/病院$/', $name)) return QuarkTypesTable::TYPE_HOSPITAL;
    if (preg_match('/[区町村]$/', $name)) return QuarkTypesTable::TYPE_ADMN_AREA;
    if (preg_match('/市$/', $name)) return QuarkTypesTable::TYPE_CITY;
    
    $txt = self::parseInfoBox($xml);
    if (!$txt) return false;

    if (preg_match('/(小学校|初等部)$/', (string)$txt[0]->th)) return QuarkTypesTable::TYPE_ELEM_SCHOOL;
    if (preg_match('/中(学校|等部)$/', (string)$txt[0]->th)) return QuarkTypesTable::TYPE_MID_SCHOOL;
    if (preg_match('/高((等学)?校|等部)$/', (string)$txt[0]->th)) return QuarkTypesTable::TYPE_HIGH_SCHOOL;
    if (preg_match('/(幼稚|保育)園$/', (string)$txt[0]->th)) return QuarkTypesTable::TYPE_PRE_SCHOOL;
    if (preg_match('/(塾|予備校)$/', (string)$txt[0]->th)) return QuarkTypesTable::TYPE_SCHOOL;

    // check if it's a country
    $txtCountry = self::parseInfoBoxCountry($xml);
    if ($txtCountry) return QuarkTypesTable::TYPE_COUNTRY;

    $label_count = 0;
    $chart_rank_count = 0;
    $member_count = 0;
    foreach($txt as $val) {
      $title = trim(strip_tags($val->th->asXML()));
      //$title = trim((string)$val->th);
      //if (empty($title) && property_exists($val->th, 'a')) {
      //  $title = (string)$val->th->a;
      //}

      // person
      if (in_array($title, ['国籍','出生地','性別','身長','生誕','生年月日','死没','職業','前職','現職','出身校','所属政党',
			    '配偶者','親族','子供'])) return QuarkTypesTable::TYPE_PERSON;
      // movie
      if (in_array($title, ['上映時間','脚本','監督','原作','主題歌','配給','出演者'])) return QuarkTypesTable::TYPE_MOVIE;

      // corporation
      if (in_array($title, ['本社所在地','事業内容','資本金','従業員数','会計監査人','決算期','主要株主','主要子会社','発行済株式総数',
			    '売上高','営業利益','経常利益','純利益','純資産','総資産'])) return QuarkTypesTable::TYPE_CORPORATION;

      // university
      if (in_array($title, ['大学設置','大学設置/創立','キャンパス','学部','研究科','学部生数','大学院生数']))
	return QuarkTypesTable::TYPE_UNIVERSITY;

      // publication issue
      if (in_array($title, ['刊行頻度','刊行期間','編集長'])) return QuarkTypesTable::TYPE_PUBLICATIONISSUE;

      // book
      if (in_array($title, ['著者','発行日','発行元','出版社'])) return QuarkTypesTable::TYPE_BOOK;

      // software application
      if (in_array($title, ['プログラミング言語','対応OS','OSの系統','ソースモデル','最新安定版リリース','使用できる言語','パッケージ管理',
			    '対応プラットフォーム','カーネル種別'])) return QuarkTypesTable::TYPE_SOFTWARE;

      // game
      //if (in_array($title, [''])) return QuarkTypesTable::TYPE_GAME;

      // goverment organization
      if (in_array($title, ['大臣','副大臣','大臣政務官','事務次官','内部部局','地方支分部局']))
	return QuarkTypesTable::TYPE_GOVERNMENT;
      
      // hospital
      if (in_array($title, ['標榜診療科','許可病床数','二次医療圏'])) return QuarkTypesTable::TYPE_HOSPITAL;

      // 音楽関係
      if (strcmp($title,'レーベル') === 0) $label_count++;
      if (strcmp($title,'チャート最高順位') === 0) $chart_rank_count++;

      // music recording
      if (in_array($title, ['作詞・作曲','録音'])) return QuarkTypesTable::TYPE_MUSIC_REC;

      // music album
      if (preg_match('/スタジオ・アルバム/', $title)) return QuarkTypesTable::TYPE_MUSIC_ALBUM;

      // music group
      if (strcmp($title,'メンバー') === 0) $member_count++;
      if (($member_count >= 1) && ($label_count >= 1)) return QuarkTypesTable::TYPE_MUSIC_GROUP;

    }
    if (!$txt || !is_string($txt)) return false;

    /* $txt = preg_replace('/\[\d+\]/', '', $txt); */
    /* $txt = preg_replace('/\A.*（.*）.*は、/', '', $txt); */
    /* $txt = preg_replace('/である。?$/', '', $txt); */
    /* $txt = preg_replace('/\A転送先:/', '', $txt); */
    
    /* return U::abbreviateStr($txt, 254); */
  }
  public static function retrieveDescription($xml)
  {
    $txt = self::retrieveFirstP($xml);
    if (!$txt || !is_string($txt)) return false;

    $txt = preg_replace('/\[\d+\]/', '', $txt);
    $txt = preg_replace('/\A.*（.*）.*は、/', '', $txt);
    $txt = preg_replace('/である。?$/', '', $txt);
    $txt = preg_replace('/\A転送先:/', '', $txt);
    
    return U::abbreviateStr($txt, 254);
  }
  public static function retrieveStart($xml)
  {
    if ($ret = self::retrieveStartItemFromInfobox($xml)) return $ret;
    return  self::retrieveStartItemFromFirstP($xml);
  }
  public static function retrieveEnd($xml)
  {
    if ($ret = self::retrieveEndItemFromInfobox($xml)) return $ret;
    return  self::retrieveEndItemFromFirstP($xml);
  }
  public static function retrieveStartItemFromFirstP($xml)
  {
    $txt = self::retrieveFirstP($xml);
    if (!$txt) return false;
    return U::getStartDateFromText($txt);
  }
  public static function retrieveEndItemFromFirstP($xml)
  {
    $txt = self::retrieveFirstP($xml);
    if (!$txt) return false;
    return U::getEndDateFromText($txt);
  }
  public static function retrieveFirstP($xml)
  {
    return self::getPlainText($xml->p);
  }
  public static function retrieveStartItemFromInfobox($xml)
  {
    $element = self::parseInfoBox($xml);
    if (!$element) return false;

    foreach($element as $val) {
      if (!property_exists($val, 'th')) continue;
      if (!property_exists($val, 'td')) continue;

      // TODO: This covers only when the element is a person or movie. Add logic for some other types of elements
      if (self::$contentType == self::CONTENT_TYPE_PERSON) {
	if (!self::isBirthdayItem((string)$val->th)) continue;
      } elseif (self::$contentType == self::CONTENT_TYPE_MOVIE) {
	if (!self::isReleasedayItem((string)$val->th)) continue;
      } elseif (self::$contentType == self::CONTENT_TYPE_BOOK ||
		self::$contentType == self::CONTENT_TYPE_MANGA) {
	if (self::isPeriod((string)$val->th)) {
	  $txt = self::getPlainText($val->td);
	  if (!$txt) return false;
	  return U::getStartDateFromText($txt);
	}
	if (!self::isReleasedayItem((string)$val->th)) continue;
      } else {
	if (self::isPeriod((string)$val->th)) {
	  $txt = self::getPlainText($val->td);
	  if (!$txt) return false;
	  return U::getStartDateFromText($txt);
	}
	if (!self::isBirthdayItem((string)$val->th) &&
	    !self::isReleasedayItem((string)$val->th)) continue;
      }

      $txt = self::getPlainText($val->td);
      if (!$txt) return false;
      $txt = U::salvageDateFromText($txt);
      if (!$txt) return false;
      return U::normalizeDateFormat($txt);
    }
    return false;
  }
  public static function retrieveEndItemFromInfobox($xml)
  {
    $element = self::parseInfoBox($xml);
    if (!$element) return false;

    foreach($element as $val) {
      if (!property_exists($val, 'th')) continue;
      if (!property_exists($val, 'td')) continue;

      // TODO: This covers only when the element is a person. Add logic for some other types of elements
      if (self::$contentType == self::CONTENT_TYPE_PERSON) {
	if (!self::isDeathdayItem((string)$val->th)) continue;
      } elseif (self::$contentType == self::CONTENT_TYPE_MANGA) {
	if (self::isPeriod((string)$val->th)) {
	  $txt = self::getPlainText($val->td);
	  if (!$txt) return false;
	  return U::getEndDateFromText($txt);
	}
	if (!self::isReleasedayItem((string)$val->th)) continue;
      } else {
	if (self::isPeriod((string)$val->th)) {
	  $txt = self::getPlainText($val->td);
	  if (!$txt) return false;
	  return U::getEndDateFromText($txt);
	}
	if (!self::isDeathdayItem((string)$val->th) &&
	    !self::isReleasedayItem((string)$val->th)) continue;
      }

      $txt = self::getPlainText($val->td);
      if (!$txt) return false;
      $txt = U::salvageDateFromText($txt);
      if (!$txt) return false;
      return U::normalizeDateFormat($txt);
    }
    return false;
  }




  public static function retrieveUrl($xml)
  {
    if ($ret = self::retrieveUrlFromInfobox($xml)) return $ret;
    return  self::retrieveUrlFromContent($xml);
  }
  public static function retrieveUrlFromInfobox($xml)
  {
    $element = self::parseInfoBox($xml);
    if (!$element) return false;

    foreach($element as $val) {
      if (!property_exists($val, 'th')) continue;
      if (!property_exists($val, 'td')) continue;

      if (!self::isUrlItem((string)$val->th)) continue;

      if (!property_exists($val->td, 'a') || !$val->td->a->attributes()) return false;
      return (string)$val->td->a->attributes()->href;
    }
    return false;
  }
  public static function retrieveUrlFromContent($xml)
  {
    // get the element next to 外部リンク header
    $target = false;
    $flg = false;
    foreach($xml as $val) {
      if ($flg) {
	$target = $val;
	break;
      } else {
	$txt = self::getPlainText($val);
	if (preg_match('/\A外部リンク/', $txt, $matches)) $flg = true;
      }
    }
    if (!$target) return false;

    if (!is_object($target)
	|| !property_exists($target, 'li')
	|| empty($target->li)
	|| !is_object($target->li[0])
	|| !property_exists($target->li[0], 'a')
	|| !$target->li[0]->a->attributes()
    ) return false;
    return (string)$target->li[0]->a->attributes()->href;
  }

  // sample:   https://upload.wikimedia.org/wikipedia/commons/4/49/SONY_VAIO_%E7%9F%B3%E7%94%B0%E7%B4%94%E4%B8%80_%E4%BD%90%E7%94%B0%E7%9C%9F%E7%94%B1%E7%BE%8E.jpg
  public static function retrieveImagePath($xml)
  {
    $src = self::readImageSrcFromInfoBox($xml);
    if (!$src) {
      $src = self::readImageSrcFromThumbinner($xml);
      if (!$src) return false;
    }

    // get file name
    $res = preg_match('/\/([^\/]*?)\.(jpg|JPG|jpeg|JPEG|gif|GIF|png|PNG)\//', $src, $matches);
    if (!$res) return false;
    $encoded_imagename = str_replace('/', '', $matches[0]);

    // get number part
    $res = preg_match('/\/\/upload\.wikimedia\.org\/wikipedia\/commons\/thumb\/(([^\/]*?)\/([^\/]*?))\//', $src, $matches);
    if (!$res) return false;
    $number_part = $matches[1];

    // generate image path
    $template = 'https://upload.wikimedia.org/wikipedia/commons/%s/%s';
    return sprintf($template, $number_part, $encoded_imagename);
  }

  public static function readImageSrcFromThumbinner($xml)
  {
    if (!is_object($xml)) return false;

    $xpath = '//div[contains(@class,"thumbinner")]';
    $element = @$xml->xpath($xpath);
    if (!is_array($element) || empty($element) || !$element[0] ||
	!is_object($element[0]) || !property_exists($element[0], 'a') || !property_exists($element[0]->a, 'img') ||
	!$element[0]->a->img->attributes()) return false;

    // prepare source
    return (string)$element[0]->a->img->attributes()->src;
  }

  public static function readImageSrcFromInfoBox($xml)
  {
    $element = self::parseInfoBox($xml);
    if (!$element) return false;

    // image is always on the second element in "infobox" class
    if (!$element[1] || !self::isImageItem($element[1])) return false;

    // prepare source
    return (string)$element[1]->td->a->img->attributes()->src;
  }

  public static function isImageItem($element)
  {
    if (!is_object($element) || !property_exists($element, 'td') || !property_exists($element->td, 'a') ||
	!$element->td->a->attributes()) return false;
    return ((string)$element->td->a->attributes()->class == 'image');
  }

  /*********************************************************************************/
  /* gluons                                                                        */
  /*********************************************************************************/
  public static function readPageForGluons($query)
  {
    $xml = self::readPage($query);
    if (!$xml) return false;

    $element = self::parseInfoBox($xml);
    if (!$element) return false;

    $relatives = [];
    $directors = [];
    $scenario_writers = [];
    $original_authors = [];
    $actors = [];
    if (self::$contentType == self::CONTENT_TYPE_PERSON) {
      // find relative information
      foreach($element as $val) {
	$res = self::findRelatives($val);
	if ($res) {
	  $relatives = array_merge($relatives, $res);
	}
      }
    } elseif (self::$contentType == self::CONTENT_TYPE_MOVIE) {
      foreach($element as $val) {
	// 監督
	$res = self::findDirector($val);
	if ($res) {
	  $directors = array_merge($directors, $res);
	}
	// 脚本
	$res = self::findScenarioWriter($val);
	if ($res) {
	  $scenario_writers = array_merge($scenario_writers, $res);
	}
	// 原作
	$res = self::findOriginalAuthor($val);
	if ($res) {
	  $original_authors = array_merge($original_authors, $res);
	}
	// 出演者
	$res = self::findActor($val);
	if ($res) {
	  $actors = array_merge($actors, $res);
	}
      }
    // TODO: 未テスト。エラーが出たので修正。なんで今まで continueで通っていたのか不明。
    //} else continue;
    } else return false;


    return ['relatives' => $relatives,
	    'scenario_writers' => $scenario_writers,
	    'original_authors' => $original_authors,
	    'actors' => $actors,
	    'directors' => $directors];
  }

  public static function findDirector($element)
  {
    if (!property_exists($element, 'th')) return false;
    if (!property_exists($element, 'td')) return false;
    if (!self::isDirectorItem((string)$element->th)) return false;

    $list = self::getPlainList($element->td);
    if (!$list) return false;

    $arr = [];
    foreach($list as $val) {
      $res = self::parseName($val);
      if ($res) {
	$arr[] = $res;
      }
    }
    return $arr;
  }
  public static function findScenarioWriter($element)
  {
    if (!property_exists($element, 'th')) return false;
    if (!property_exists($element, 'td')) return false;
    if (!self::isScenarioWriterItem((string)$element->th)) return false;

    $list = self::getPlainList($element->td);
    if (!$list) return false;

    $arr = [];
    foreach($list as $val) {
      $res = self::parseName($val);
      if ($res) {
	$arr[] = $res;
      }
    }
    return $arr;
  }
  public static function findOriginalAuthor($element)
  {
    if (!property_exists($element, 'th')) return false;
    if (!property_exists($element, 'td')) return false;
    if (!self::isOriginalAuthorItem((string)$element->th)) return false;

    $list = self::getPlainList($element->td);
    if (!$list) return false;

    $arr = [];
    foreach($list as $val) {
      $res = self::parseName($val);
      if ($res) {
	$arr[] = $res;
      }
    }
    return $arr;
  }
  public static function findActor($element)
  {
    if (!property_exists($element, 'th')) return false;
    if (!property_exists($element, 'td')) return false;
    if (!self::isActorItem((string)$element->th)) return false;

    $list = self::getPlainList($element->td);
    if (!$list) return false;

    $arr = [];
    foreach($list as $val) {
      $res = self::parseName($val);
      if ($res) {
	$arr[] = $res;
      }
    }
    return $arr;
  }


  public static function findRelatives($element)
  {
    if (!property_exists($element, 'th')) return false;
    if (!property_exists($element, 'td')) return false;
    $str = (string)$element->th;
    if (!self::isRelativesItem($str)) return false;

    $list = self::getPlainList($element->td);
    if (!$list) return false;

    if (self::isFarther($str)) {
      $type = '父';
    } elseif (self::isMother($str)) {
      $type = '母';
    } elseif (self::isChildren($str)) {
      $type = '子供';
    } else $type = false;

    $arr = [];
    foreach($list as $val) {
      $res = self::parseRelative($val, $type);
      if (!$res) continue;
      $arr[] = $res;
    }

    if (empty($arr)) return false;
    return $arr;
  }

  public static function parseName($str)
  {
    $replaced1 = preg_replace('/（(.*)）/', "", $str);
    $ret = preg_replace('/『(.*)』/', "", $replaced1);
    return empty($ret) ? false : $ret;
  }

  public static function parseRelative($str, $type = false)
  {
    $main = preg_replace('/（(.*)）/', "", $str);
    $main = preg_replace('/\((.*)\)/', "", $main);
    $main = preg_replace('/\[(.*)\]/', "", $main);
    $main = U::trimSpace($main);

    $relative_type = false;

    // try exploding
    $rest = false;
    $tmp = explode('：', $main);
    if (count($tmp) > 1) {
      foreach($tmp as $val) {
	if (GlobalDataSet::isRelativeType($val)) {
	  $relative_type = $val;
	} else {
	  $rest = $val;
	}
      }
    }

    if (!$relative_type) {
      $tmp = explode('・', $main);
      if (count($tmp) > 1) {
	foreach($tmp as $val) {
	  if (GlobalDataSet::isRelativeType($val)) {
	    $relative_type = $val;
	  } else {
	    $rest = $val;
	  }
	}
      }
    }
    if ($relative_type) {
      $main = $rest;
    }
    if (!$main) return false;

    if (!$relative_type) {
      $res = preg_match_all('/（(.*?)）/', $str, $matches);
      if ($res && is_array($matches) and count($matches) >= 2) {
	foreach($matches[1] as $val) {
	  if (GlobalDataSet::isRelativeType($val)) {
	    $relative_type = $val;
	  }
	}
      }
    }

    if (!$relative_type) {
      $res = preg_match('/\A.*?\[(.*?)\].*?\z/', $str, $matches);
      if ($res && is_array($matches) and count($matches) >= 2) {
	foreach($matches[1] as $val) {
	  if (GlobalDataSet::isRelativeType($val)) {
	    $relative_type = $val;
	  }
	}
      }
    }
    if (!$relative_type) {
      if (!$type) return false;
      $relative_type = $type;
    }

    return ['main' => $main, 'relative_type' => $relative_type, 'source' => 'wikipedia'];
  }

  /*********************************************************************************/
  /* Tools                                                                         */
  /*********************************************************************************/
  public static function getPlainList($element)
  {
    $txt = self::getPlainText($element);
    $tmp = explode("\n", $txt);
    return array_values(array_filter($tmp, "strlen"));
  }
  public static function getPlainText($element)
  {
    return strip_tags(preg_replace('/\n?\<br\/\>\n?/',"\n",$element->asXml()));
  }


  public static function isDirectorItem($str)
  {
    return ((strcmp($str, '監督') === 0) 
	    );
  }
  public static function isScenarioWriterItem($str)
  {
    return ((strcmp($str, '脚本') === 0) 
	    );
  }
  public static function isOriginalAuthorItem($str)
  {
    return ((strcmp($str, '原作') === 0) 
	    );
  }
  public static function isActorItem($str)
  {
    return ((strcmp($str, '出演者') === 0) ||
	    (strcmp($str, 'キャスト') === 0)
	    );
  }
  public static function isRelativesItem($str)
  {
    return ((strcmp($str, '著名な家族') === 0) ||
	    (strcmp($str, '親族') === 0) ||
	    self::isFarther($str) ||
	    self::isMother($str) ||
	    self::isChildren($str)
	    );
  }
  public static function isFarther($str)
  {
    return (strcmp($str, '父親') === 0);
  }
  public static function isMother($str)
  {
    return (strcmp($str, '母親') === 0);
  }
  public static function isChildren($str)
  {
    return ((strcmp($str, '子女') === 0) ||
	    (strcmp($str, '子供') === 0));
  }

  public static function isBirthdayItem($str)
  {
    return ((strcmp($str, '生年月日') === 0) ||
	    (strcmp($str, '出生') === 0) ||
	    (strcmp($str, '誕生') === 0) ||
	    (strcmp($str, '生誕') === 0)
	    );
  }
  public static function isReleasedayItem($str)
  {
    return ((strcmp($str, '公開') === 0)
	    );
  }
  public static function isDeathdayItem($str)
  {
    return ((strcmp($str, '没年月日') === 0) ||
	    (strcmp($str, '死没') === 0) ||
	    (strcmp($str, '崩御') === 0)
	    );
  }
  public static function isPeriod($str)
  {
    return ((strcmp($str, '発表期間') === 0) ||
	    (strcmp($str, '放送期間') === 0)
	    );
  }

  public static function isUrlItem($str)
  {
    return ((strcmp($str, '公式サイト') === 0) ||
	    (strcmp($str, '外部リンク') === 0)
	    );
  }

  /*********************************************************************************/
  /* API                                                                           */
  /*********************************************************************************/
  // $option = [
  //	       'action' => 'query',
  //	       'titles' => 'エマ・ワトソン',
  //	       'prop' => 'revisions',
  //	       'rvprop' => 'content',
  //	       ];
  public static $is_markdown = true;
  public static function callByTitle($title)
  {
    $option = ['titles' => $title];
    $option['action'] = 'query';
    $option['prop'] = 'revisions';
    $option['rvprop'] = 'content';
    if (!self::$is_markdown) $option['rvparse'] = '';
    $data = self::call($option);
    if (!$data) return false;

    if (!is_object($data) || !property_exists($data, 'query') || !property_exists($data->query, 'pages') ||
	!property_exists($data->query->pages, 'page') || !$data->query->pages->page->attributes() ||
	!property_exists($data->query->pages->page, 'revisions') ||
	!property_exists($data->query->pages->page->revisions, 'rev')) return false;

    $pageid = (int)$data->query->pages->page->attributes()->pageid;
    $title = (string)$data->query->pages->page->attributes()->title;
    $content_md = (string)$data->query->pages->page->revisions->rev;

    if (self::$is_markdown) {
      $content = $content_md;
    } else {
      $obj = U::simplexml_from_html('<html><title></title>'.$content_md.'</html>');
      $content = $obj->body->div;
    }
    return compact('pageid', 'title', 'content');
  }

  public static function call($option = [])
  {
    $endpoint = 'https://ja.wikipedia.org/w/api.php';
    $option['format'] = 'xml';
    $query = http_build_query($option);
    if (!$query) return false;
    U::$retrieveCacheConfig = self::$retrieveCacheConfig;
    return U::retrieveXmlFromUrl($endpoint . '?' . $query);
  }


  public static function readApiForQuark($query)
  {
    self::$is_markdown = false;
    $dataset = self::callByTitle($query);
    if (!$dataset ||
	self::isRedirect($dataset['content']) ||
	self::isDisambig($dataset['content'])) return false;

    self::$internal = true;
    self::$contentType = self::CONTENT_TYPE_NONE;
    return self::readPageByXmlForQuark($dataset['title'], $dataset['content'], $dataset['pageid']);
  }

  public static function isRedirect($content)
  {
    if (!($content instanceof \SimpleXMLElement)) return false;
    $element = @$content->xpath(self::$xpath_redirect);
    return ($element);
  }
  public static function isDisambig($content)
  {
    if (!($content instanceof \SimpleXMLElement)) return false;
    $element = @$content->xpath(self::$xpath_disambig);
    return ($element);
  }
}
