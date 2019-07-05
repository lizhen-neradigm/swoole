<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use App\Handlers\SwooleHandler;
use Illuminate\Support\Facades\Redis;
class swoole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swoole:action {action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test swoole socket';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->fire();
    }

    public function fire(){
        $arg=$this->argument('action');
        // $this->info($arg);
        // exit();
        switch($arg){
            case 'start':
                $this->info('swoole observer started');
                $this->start();
                break;
            case 'stop':
                $this->info('stoped');
                break;
            case 'restart':
                $this->info('restarted');
                break;
        }
    }

    public function start(){
        $this->serv=new \swoole_websocket_server('0.0.0.0',9502);
        
        $this->serv->set(
            array(
                'worker_num'=>4,
                'daemonize'=>1,
                'log_file'=>env('SWOOLE_HTTP_PID_FILE', base_path('storage/logs/swoole.log')),
                'pid_file' => env('SWOOLE_HTTP_PID_FILE', base_path('storage/logs/swoole_http.pid')),
                'max_request'=>100,
                'dispatch_mode'=>2,
                'debug_mode'=>1
            )
        );

        $handler=SwooleHandler::getInstance();
        //var_dump($this->serv);
        // exit();
        

        $this->serv->on('open',array($handler,'onOpen'));
        
        $this->serv->on('workerstart',array($handler,'onWorkStart'));
        
        //$values = Redis::get('name');
        //dd($values);
        //$this->info($values);
        
        //$this->serv->on('Start',array($handler,'onStart'));
        $this->serv->on('Connect',array($handler,'onConnect'));
        $this->serv->on('message',array($handler,'onMessage'));
        //$this->serv->on('Receive',array($handler,'onReceive'));
        $this->serv->on('close',array($handler,'onClose'));

        $this->serv->start();
    }

    protected function getArguments(){
        return array(
            'action',InputArgument::REQUIRED,'start|stop|restart'
        );
    }
}