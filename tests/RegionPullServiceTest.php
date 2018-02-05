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
        $this->regionPull = new RegionPullService('http://www.mca.gov.cn/article/sj/tjbz/a/2017/201801/201801151447.html');
    }

    public function test_resolve()
    {
        file_put_contents("./data/region.sql", $this->regionPull->resolve());
        // print_r($this->regionPull->resolve());
    }
}