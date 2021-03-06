<?php
/**
 * Created by PhpStorm.
 * User: hiliq
 * Date: 2019/1/2
 * Time: 11:31
 */

namespace app\index\controller;

use app\model\Author;
class Authors extends Base
{
    public function index(){
        $id = input('id');
        $author = Author::with('books')->find($id);
        $this->assign([
            'author' => $author,
            'header_title' => '作者主页',
        ]);
        return view($this->tpl);
    }
}