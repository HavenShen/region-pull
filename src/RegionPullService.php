<?php

namespace HavenShen\Region\Pull;

use Overtrue\Pinyin\Pinyin;

/**
 * RegionPullService
 *
 * @author    Haven Shen <havenshen@gmail.com>
 * @copyright    Copyright (c) Haven Shen
 */
class RegionPullService
{

    private $tabName = 'region';

    // 直辖市
    private $city = array('北京市', '天津市', '上海市', '重庆市');


    public function __construct($url = null)
    {
        $url === null || $this->url = $url;
    }

    public function resolve()
    {
        $str = $this->get($this->url);
        $list = $this->getList($str);
        $struct = $this->structuring($list);
        $sql = $this->buildSql($struct);
        return $sql;
    }

    private function get($url)
    {
        return file_get_contents($url);
    }

    private function getList($str)
    {
        $pattern = '/<td class=xl\d+>(\d+)<\/td>\n*\s*<td class=xl\d+>(.+?)<\/td>/';
        if (!preg_match_all($pattern, $str, $arr)) {
            throw new Exception('正则匹配失败');
        }
        // $result: ['code'=>110000, 'name'=>'北京市']
        $result = array();
        for ($i=0; $i < count($arr[1]); $i++) {
            $result[] = array('code' => $arr[1][$i], 'name' => $this->stripTags($arr[2][$i]));
        }

        return $result;
    }

    private function stripTags($str)
    {
        return trim(str_replace("\xc2\xa0", '', strtr($str, array(
            "<span style='mso-spacerun:yes'>" => '',
            "</span>" => '',
        ))), '  ');
    }

    private function trimC2A0($str)
    {
        return trim();
    }

     /**
     * 结构化城市数据
     * @param  array $list 列表数据
     * @return array       ['code'=>, 'name'=>'','sub'=> ['code','name'=>'', sub'=>[...]]]
     */
    private function structuring($list)
    {
        if (empty($list)) {
            throw new Exception('列表为空');
        }

        // 省份
         $province = array_filter($list, function($v) {
            return $v['code'] % 10000 === 0;
        });

        // 城市 & 地区
         foreach ($province as &$pro) {
            if (in_array($pro['name'], $this->city)) {
                $citys = array($pro);
                $citys[0]['code'] += 100;
                $area = array_filter($list, function ($v) use($pro) {
                    return !strncmp($v['code'], $pro['code'], 2)
                        && $v['code'] % 10000 !== 0;
                });

                $area = $this->withAdditional($area);

                $citys[0]['sub'] = $area;
            } else {
                $citys = array_filter($list, function($v) use($pro) {
                    return !strncmp($v['code'], $pro['code'], 2)
                        && $v['code'] % 100 === 0
                        && $v['code'] % 10000 !== 0;
                });

                foreach ($citys as &$city) {
                    $area = array_filter($list, function($v) use($city) {
                        return !strncmp($v['code'], $city['code'], 4)
                            && $v['code'] % 100 !== 0;
                    });
                    $area = $this->withAdditional($area);
                    $city['sub'] = $area;
                }
            }

            $citys = $this->withAdditional($citys);
            $pro['sub'] = $citys;
        }

        $province = $this->withAdditional($province);

        return $province;
    }

    private function explodeName(string $str)
    {
        $name = $str;

        $suffixArr = ['特别行政区', '自治区', '自治州', '自治旗', '地区', '自治县',  '行委', '堂区','林区', '镇', '乡', '区', '县', '市', '旗', '盟', '省'];

        $suffix = $this->searchStr($suffixArr, $str);

        $extraArr = ['仡佬族苗族','仫佬族','佤族','侗族','保安族东乡族撒拉族','傈僳族','傣族','傣族佤族,傣族彝族','傣族拉祜族佤族','傣族景颇族','各族','哈尼族','哈尼族彝族','哈尼族彝族傣族','哈萨克','哈萨克族','回族','回族土族','回族彝族','土家族','土家族苗族','土族','塔吉克','壮族','壮族瑶族','壮族苗族','布依族苗族','彝族','彝族傣族','彝族哈尼族拉祜族','彝族回族','彝族回族苗族','彝族苗族','拉祜族','拉祜族佤族布朗族傣族','撒拉族','朝鲜族','柯尔克孜','毛南族','水族','满族','满族蒙古族','独龙族怒族','瑶族','畲族','白族','白族普米族','矿','纳西族','维吾尔','羌族','联合','苗族','苗族侗族','苗族土家族','苗族布依族','苗族瑶族傣族','蒙古','蒙古族','蒙古族藏族','藏族','藏族羌族','裕固族','达斡尔族','黎族','黎族苗族'];

        $exclude = ['内蒙古'];

        $extra = $this->searchStr($extraArr, $str);

        if ($suffix) {
            $name = explode($suffix, $str)[0];
        }

        if ($extra && !in_array($name, $exclude)) {
            $name = explode($extra, $name)[0];
        }

        return [$name,$extra,$suffix];
    }

    private function searchStr(array $filter, string $str)
    {
        $strLen = 0;
        $dataStr = null;
        foreach ($filter as $item) {
            if (mb_strripos($str, $item)) {
                $itemStrLen = mb_strlen($item);
                if ($itemStrLen > $strLen) {
                    $strLen = $itemStrLen;
                    $dataStr = $item;
                }

                if ($itemStrLen == $strLen) {
                    $strLen = $itemStrLen;
                    $dataStr = $item;
                }
            }
        }

        return $dataStr;
    }

    private function withAdditional($arr)
    {
        $pinyin = new Pinyin();
        $data = [];
        foreach ($arr as $item) {
            list($name, $extra, $suffix) = $this->explodeName($item['name']);
            $item['full_name'] = $item['name'];
            $item['name'] = $name;
            $item['extra'] = $extra;
            $item['suffix'] = $suffix;
            $item['initial'] = $pinyin->abbr(mb_substr($name, 0, 1));
            $item['initials'] = $pinyin->abbr($name);
            $item['pinyin'] = preg_replace('/[ ]/', '', $pinyin->sentence($name));
            $data[] = $item;
        }

        return $data;
    }

    private function buildSql($arr)
    {
        if (empty($arr)) {
            throw new Exception('城市数据为空');
        }

        $data = array();
        $id = 0;
        foreach ($arr as $pro) {
            $pid = ++$id;
            $data[] = array(
                'id' => $pid,
                'pid' => 0,
                'code' => $pro['code'],
                'name' => $pro['name'],
                'full_name' => $pro['full_name'],
                'initials' => $pro['initials'],
                'extra' => $pro['extra'],
                'suffix' => $pro['suffix'],
                'initial' => $pro['initial'],
                'pinyin' => $pro['pinyin'],
                'type' => 'province',
            );
            foreach ($pro['sub'] as $city) {
                $pid2 = ++$id;
                $data[] = array(
                    'id' => $pid2,
                    'pid' => $pid,
                    'code' => $city['code'],
                    'name' => $city['name'],
                    'full_name' => $city['full_name'],
                    'initials' => $city['initials'],
                    'extra' => $city['extra'],
                    'suffix' => $city['suffix'],
                    'initial' => $city['initial'],
                    'pinyin' => $city['pinyin'],
                    'type' => 'city',
                );
                foreach ($city['sub'] as $area) {
                    $data[] = array(
                        'id' => ++$id,
                        'pid' => $pid2,
                        'code' => $area['code'],
                        'name' => $area['name'],
                        'full_name' => $area['full_name'],
                        'initials' => $area['initials'],
                        'extra' => $area['extra'],
                        'suffix' => $area['suffix'],
                        'initial' => $area['initial'],
                        'pinyin' => $area['pinyin'],
                        'type' => 'area',
                    );
                }
            }
        }

        $sql = "CREATE TABLE IF NOT EXISTS `$this->tabName`(\n    `id` SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    `pid` SMALLINT UNSIGNED NOT NULL COMMENT '父级 ID',\n    `code` MEDIUMINT UNSIGNED NOT NULL COMMENT '区划代码',\n    `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '名称',\n    `full_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '名称',\n    `initial` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '首字母',\n    `initials` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '每个汉字首字母',\n    `pinyin` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '拼音',\n    `extra` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '特殊命名',\n    `suffix` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '后缀',\n    `type` enum('province','city','area') NOT NULL DEFAULT 'area' COMMENT '类别',\n    INDEX `pid`(`pid`)\n) engine=innodb default charset=utf8;\n\n";
        $len = count($data);
        foreach ($data as $k => $v) {
            if ($k % 2000 == 0) {
                $sql .= "INSERT INTO {$this->tabName} (id,pid,code,name,full_name,initial,initials,pinyin,extra,suffix,type) VALUES\n";
            }
            if (($k + 1) % 2000 == 0 || $len == $k+1) {
                $sql .= "({$v['id']},{$v['pid']},{$v['code']},'{$v['name']}','{$v['full_name']}','{$v['initial']}','{$v['initials']}','{$v['pinyin']}','{$v['extra']}','{$v['suffix']}','{$v['type']}');\n\n";
            } else {
                $sql .= "({$v['id']},{$v['pid']},{$v['code']},'{$v['name']}','{$v['full_name']}','{$v['initial']}','{$v['initials']}','{$v['pinyin']}','{$v['extra']}','{$v['suffix']}','{$v['type']}'),\n";
            }
        }
        return $sql;
    }

}
