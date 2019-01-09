<?php
/**
 * Created by PhpStorm.
 * User: zhangxiang
 * Date: 2018/11/18
 * Time: 5:26 PM
 */

namespace app\admin\controller;

use think\Request;
use app\model\Book;
use app\model\Chapter;
use app\service\ChapterService;
use think\facade\App;

class Chapters extends Base
{
    protected $chapterService;
    public function initialize()
    {
        $this->chapterService = new ChapterService();
    }

    public function index($book_id)
    {
        $data = $this->chapterService->getAdminChapters([
            ['book_id','=',$book_id]
        ]);
        $this->assign([
            'chapters' => $data['chapters'],
            'count' => $data['count'],
            'book_id' => $book_id
        ]);
        return view();
    }

    public function create($book_id){
        $this->assign('book_id',$book_id);
        return view();
    }

    public function save(Request $request)
    {
        $data = $request->param();
        if(empty($data['chapter_name'])){
            $this->error('没有填写章节名');
        }
        $map[] = ['chapter_name','=',$data['chapter_name']];
        $map[] = ['book_id','=',$data['book_id']];
        $chapter = Chapter::where($map)->find();
        if ($chapter){
            $this->error('存在同名章节');
        }
        $chapter = new Chapter();
        $result = $chapter->save($data);
        //获取内容，保存为txt文件
        $content = $data['content'];
        if (!empty($content)){
            $dir = App::getRootPath() . '/public/static/upload/book/' . $data['book_id'];
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        if ($result){
            $file = fopen($dir.'/'.$chapter->id.'.txt','w');
            fwrite($file,$content); //保存TXT文件
            $param = [
                "id" => $data["book_id"],
                "last_time" => date("Y-m-d H:i:s", time())
            ];
            Book::update($param);
            $this->redirect('index/jump');
        }else{
            $this->error('新增失败');
        }
    }

    public function edit($id)
    {
        $id = input('id');
        $chapter = Chapter::get($id);
        if (!$chapter){
            $this->error('不存在的章节');
        }
        $book_id = input('book_id');
        $this->assign([
            'book_id' => $book_id,
            'chapter' => $chapter
        ]);
        return view();
    }

    public function update(Request $request)
    {
        $book_id = input('book_id');
        $id = input('id');
        $chapter_name = input('chapter_name');
        $chapter = Chapter::get($id);
        if ($chapter){
            $chapter->chapter_name = $chapter_name;
            $chapter->save();
            $this->success('编辑成功',\url('index',['book_id' => $book_id]),'',1);
        }else{
            $this->error('章节不存在');
        }
    }

    public function delete($id)
    {
        $chapter = Chapter::get($id);
        $chapter->delete();
        return ['err'=>0,'msg'=>'删除成功'];
    }

    //展示章节内容
    public function content(Request $request){
        $book_id = $request->param('book_id');
        $chapter_id = $request->param('chapter_id');
        $path = App::getRootPath() . 'public/static/upload/book/' . $book_id.'/'.$chapter_id.'.txt';
        $file = fopen($path,'r');
        $content = fread($file,filesize($path));
        $this->assign('content',$content);
        return view();
    }

    //自动分割处理器
    public function split(Request $request){
        $book_id = $request->param('book_id');
        if ($request->isPost()){
            $file = $request->file('content');
            $dir = App::getRootPath() . '/public/static/upload/book/'.$book_id;
            $info = $file->move($dir);
            if ($info){
                $this->process($info->getSaveName(),$book_id);
            }
            $this->redirect('index/jump');
        }
        $this->assign('book_id',$book_id);
        return view();
    }

    private function process($file,$book_id){
        $book = Book::get($book_id);
        $book->last_time = date("Y-m-d H:i:s", time());
        $book->isUpdate(true)->save();
        $chapter_count = count($book->chapters);
        $path = App::getRootPath().'public/static/upload/book/'.$book_id.'/'.$file;
        $content = file_get_contents(urldecode($path));
        $encoding = mb_detect_encoding($content, "auto");
        mb_substitute_character("none");
        mb_convert_encoding($content, 'UTF-8', $encoding);
        $arr = preg_split('/[;\r\n]+/s',$content); //将文本分行转换成数组
        $new = array_chunk($arr,60); //分割成小数组
        $i = $chapter_count + 1;
        foreach ($new as $arr) {
            $chapter = new Chapter();
            $chapter->save(['chapter_name' => '第'.$i.'章', 'book_id' => $book_id]);
            $file = App::getRootPath() . '/public/static/upload/book/'.$book_id.'/'.$chapter->id.'.txt';
            foreach ($arr as $item) {
                $handle=fopen($file,"a+");
                fwrite($handle,$item."\n");
                fclose($handle);
            }
            $i++;
        }

    }
}