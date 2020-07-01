<?php

namespace Tests;

use HavenShen\Region\Pull\RegionPullService;

/**
 * RegionPullServiceTest
 *
 * @author    Haven Shen <havenshen@gmail.com>
 * @copyright    Copyright (c) Haven Shen
 */
class RegionPullServiceTest extends \PHPUnit\Framework\TestCase
{
    protected $regionPull;

    public function setUp()
    {
        $this->regionPull = new RegionPullService('http://www.mca.gov.cn/article/sj/xzqh/2020/2020/202003301019.html');
    }

    public function test_resolve()
    {
        // $str = '   延庆区';
        // var_dump();die;
        file_put_contents("./data/region.sql", $this->regionPull->resolve());
        // print_r($this->regionPull->resolve());
    }
}