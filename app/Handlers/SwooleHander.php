<?php

namespace App\Handlers;

//use App\Models\OrderGoods;
use Illuminate\Support\Facades\Log;
//use App\Models\StoreUser;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class SwooleHandler
{

    private static $_instance;    //保存类实例的私有静态成员变量

    private $redis;

    public function __construct()
    {

    }

    //定义私有的__clone()方法，确保单例类不能被复制或克隆
    private function __clone() {}

    //对外提供获取唯一实例的方法
    public static function getInstance()
    {
        //检测类是否被实例化
        if ( ! (self::$_instance instanceof self) ) {
            self::$_instance = new SwooleHandler();
        }
        // var_dump(self::$_instance);
        // exit();
        return self::$_instance;
    }

    public function onWorkStart($serv, $rq)
    {   

        $redis = Redis::connection();
        $serv->redis = $redis;
        Redis::set('name', '312啊啊啊');
        echo "Swoole onWorkStart ";
    }
    public function onOpen($serv, $request)
    {   
        // $c_id = DB::table('kefu')->where('id', 1)->value('c_id');
        $c_id = 1;
        //print_r($c_id);
        // exit();
        $g_id = DB::table('group')->insertGetId(['ip' => '123', 'fd_id' => $request->fd]);
        DB::table('customer')->insert(['ip' => '123', 'g_id' => $g_id]);
        DB::table('kefu')->where('id','=', $c_id)->update(['g_id' => $g_id]);
        print_r($request);
        //$ip = $request->getClientIp();
        // echo 'ip:'.$ip."\n";
        // echo 'fd:'.$request->fd;echo "<hr>";
        // print_r($request->get);
        //print_r($request->server);
        //print_r($request->fd, $request->get, $request->server);
        $serv->push($request->fd, "hello, welcome\n");
    }

    public function onStart($serv)
    {
        Redis::set('start', 'qwwq');
        Log::info('swoole start');
    }

    public function onConnect($serv, $fd, $from_id)
    {   
        Redis::set('user', $fd);
        Redis::set('from_id', $from_id);
        //$request->getClientIp();
        
        Log::info('swoole connect' . $from_id);
    }

    public function onMessage($serv, $frame)
    {
        echo "Message: {$frame->data}\n";
        $serv->push($frame->fd, "server: {$frame->data}");
        //获取token和操作
        //$this->handleByAction($serv, $frame);
        Log::info('swoole message' . json_encode($frame));
    }

    /**
     * 根据传来的字符串处理相应的内容
     * create by sunnier
     * Email:xiaoyao_xiao@126.com
     * @param $server
     * @param $str
     */
    private function handleByAction($server, $frame)
    {
        //根据,拆分所传的字符串，判断用户行为
        $str=$this->decode($frame->data);
        $arr=explode('||:||',$str);
        if(is_array($arr)) {
            //第一个是token
            $token = addslashes(strval($arr[0]));
            if(!empty($arr[1])) {
                //第二个是行为
                $action = strval($arr[1]);
                switch ($action) {
                    case 'login':
                        $this->login($token, $server, $frame);
                        break;
                    case 'delete':
                        $this->delete($token, $arr[2], $server, $frame);
                        break;
                    case 'push':
                        $this->push($token, $arr[2], $server, $frame);
                        break;
                    default:
                }
                if (!empty($arr[2])) {
                    $server->push($frame->fd, $this->encode($arr[2]));
                }
            }else{
                $server->push($frame->fd, $this->encode($arr[0]));
            }
        }else{
            $server->push($frame->fd, $this->encode($frame->data));
        }
    }
    //ascii码转换为字符串
    private function decode($M){
        $bytes=explode(',',$M);
        $str = '';
        foreach($bytes as $ch) {
            $str .= chr($ch);
        }
        return $str;
    }

    /**
     * 字符串转换为ascii码
     * create by sunnier
     * Email:xiaoyao_xiao@126.com
     * @param $message
     * @return string
     */
    private function encode($message){
        $bytes = array();
        for($i = 0; $i < strlen($message); $i++){
            $bytes[] = ord($message[$i]);
        }
        return implode(',',$bytes);
    }

    /**
     * 登录行为
     * create by sunnier
     * Email:xiaoyao_xiao@126.com
     * @param $token
     * @param $server
     * @param $frame
     */
    private function login($token,$server,$frame){
        $user=StoreUser::where('token',$token)->first();
        if(!empty($user)) {
            $server->redis->set($token, $frame->fd);
            $server->push($frame->fd, $this->encode(json_encode(["连接成功！"])));
        }else{
            $server->push($frame->fd, $this->encode(json_encode(["连接失败，未找到用户！"])));
        }
    }

    /**
     * 删除内容行为
     * create by sunnier
     * Email:xiaoyao_xiao@126.com
     * @param $token
     * @param $str
     * @param $server
     */
    private function delete($token,$str,$server,$frame){
        $user=StoreUser::where('token',$token)->first();
        if(!empty($user)) {
            //删除对应的内容
            $order_goods_id=intval($str);
            if(OrderGoods::where('id',$order_goods_id)->update(['has_finish' => 1])) {
                $server->push($frame->fd, $this->encode(json_encode(['action'=>'delete','id'=>$order_goods_id])));
            }else{
                $server->push($frame->fd, $this->encode(json_encode(["删除失败！"])));
            }
        }else{
            $server->push($frame->fd, $this->encode(json_encode(["删除失败，未找到用户！"])));
        }
    }

    /**
     * 推送行为
     * create by sunnier
     * Email:xiaoyao_xiao@126.com
     * @param $token
     * @param $str
     * @param $server
     * @param $frame
     */
    private function push($token,$str,$server,$frame){
        $user=StoreUser::where('token',$token)->first();
        //查询是否存在对应用户
        if(!empty($user)) {
            $fd =  $server->redis->get($token);
            $server->push($fd,$this->encode($str));
        }else{
            $server->push($frame->fd, $this->encode(json_encode(["推送失败，未找到用户！"])));
        }
    }

    public function onClose($serv, $fd, $from_id)
    {
        Log::info('swoole close');
    }

    public function onReceive($serv, $fd, $from_id, $data)
    {
        Log::info(json_encode($data));
    }
}
