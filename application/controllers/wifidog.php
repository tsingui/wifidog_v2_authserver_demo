<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
	wifidog 接口控制类
	
	为了说明wifidog参数传递方式，没有采用CI的input类的参数获取方式
	
 */
class Wifidog extends CI_Controller {
    private $is_mobile = false;
	private $valid_agent = true;
	
    /**
     * 构造函数
     */
	function __construct()
	{
		parent::__construct();
		//获取user_agent将来对客户端进行限定		
		$temp_str = $this->input->user_agent() ;
		
		
		//if(!(!empty($temp_str) and $temp_str == 'WiFiDog 20131017'))
		//	$this->valid_agent = false;
        
		//根据 http user_agent 判断访问者的设备类型，主要用在login，及portal接口上
		$this->is_mobile = isMobile();			
	}
	/**
     * 默认页面
     */
	public function index()
	{
		//显示空白，或者显示给普通访问者的页面
		echo "hello,wifidog!";
	}
	
	/**
     * ping心跳连接处理接口，wifidog会按照配置文件的间隔时间，定期访问这个接口，以确保认证服务器“健在”！
     */
	public function ping()
	{
		if(!$this->valid_agent)
			return;
		//url请求 "gw_id=$gw_id&sys_uptime=$sys_uptime&sys_memfree=$sys_memfree&sys_load=$sys_load&wifidog_uptime=$wifidog_uptime";
		//log_message($this->config->item('MY_log_threshold'), __CLASS__.':'.__FUNCTION__.':'.debug_printarray($_GET));
		
		//判断各种参数是否为空
		if( !(isset($_GET['gw_id']) and isset($_GET['sys_uptime']) and isset($_GET['sys_memfree']) and isset($_GET['sys_load']) and isset($_GET['wifidog_uptime']) ) )
		{
			echo '{"error":"wifidog参数提交错误"}';
			return;
		}
		//添加心跳日志处理功能
		/*
		此处可获取 wififog提供的 如下参数
		1.gw_id  来自wifidog 配置文件中，用来区分不同的路由设备
		2.sys_uptime 路由器的系统启动时间
		3.sys_memfree 系统内存使用百分比
		4.wifidog_uptime wifidog持续运行时间（这个数据经常会有问题）
		~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		v2新增加参数
		5.dev_id 设备id ，45位字符串（用来区分不同的设备）
		6.cpu_usage cpu利用率，单位% 值0-100
		7.nf_conntrack_num 系统会话数 值为整数
		8.out_rate 路由器出口（WAN口）的上行即时速率，单位 bps
		9.in_rate 路由器出口（WAN口）的下行即时速率，单位 bps
		10.(2014-08-07) online_devices 活跃主机数（包括通过认证和没有通过认证的、凡是连接到路由器上的设备的数量），
		*/
		
		//返回值
		//规则返回值
		$rule = ' result=';
		
		//主机、网段规则
		$hostrule = array(
					//一条主机规则：针对192.168.1.6单个ip，配置上行为80bps/10Bps，下行为800bps/100Bps的规则
					array('ip'=>'192.168.1.6','netmask'=>'255.255.255.255','up'=>'80','down'=>'800','session'=>'0'),
					//一条主机规则：针对192.168.1.9单个ip，配置上行不受限，下行为2400bps/300Bps的规则,生效时间：每天8点到18：30
					array('ip'=>'192.168.1.9','netmask'=>'255.255.255.255','up'=>'0','down'=>'2400','session'=>'0','timestart'=>'08:00','timestop'=>'18:30'),
					//一条网段规则：针对192.168.1.0/24整个网段，配置该网段内的所有主机上行为8000bps/1000Bps 约1KBps，下行为16000000bps/2000000Bps 约2MBps的规则
					array('ip'=>'192.168.1.0','netmask'=>'255.255.255.0','up'=>'8000','down'=>'16000000','session'=>'0','timestart'=>NULL,'timestop'=>NULL),
					//......其他规则
					);
		//注：按照先后顺序，1.6将按照第一条规则执行限速，1.9按照第二条规则限速，1.0网段其他ip将按照第三条规则限速
		//注：限速规则到后台是要用Bps的单位配置下去的（先做除8操作，再下发），所以主机限速规则要遵循如下规则：要大于8，否则配置下去的有可能是0；尽量配置成8的整倍数
		//注：因为会话数限速配置容易导致会话数被很快用完而导致正常程序上不去网的问题，故取消掉会话数限制，默认都返回0就行
		//注：（2014-07-29增加）参数timestart 和timestop表示该规则生效的时间，要求：格式“HH:MM” 24小时制，timestart和timestop必须同时有效，而且timestop必须大于timestart
		
		//ip白名单
		$ipwhite = array(
						//添加1.2为不受限速限制的白名单
						array('ip'=>'192.168.1.2','netmask'=>'255.255.255.255'),
						//添加1.254为不受限速限制的白名单
						array('ip'=>'192.168.1.254','netmask'=>'255.255.255.255'),
						//..........
					);
		//注意：netmask也可以使用网段掩码，但是就相应的整个网段都是白名单，为了避免混淆，最好固定255.255.255.255不变
		
		//mac黑名单
		//添加2个mac为mac黑名单，不能上网，不能dhcp获取ip
		$macblack = array(array('mac'=>'aa:aa:aa:aa:aa , bb.bb.bb.bb.bb.bb'));
		//注意：所有的mac地址要写在一个字符串里,中间用“,”号隔开
		
		//mac白名单
		//添加2个mac为mac白名单，不用认证就能上网
		$macwhite = array(array('mac'=>'cc.cc.cc.cc.cc.cc , dd.dd.dd.dd.dd.dd'));
		//注意：所有的mac地址要写在一个字符串里,中间用“,”号隔开
		
		//域名白名单
		//添加2个不用认证就能访问的域名
		$domain = array(array('domain'=>'szshort.weixin.qq.com,www.apfree.net'));
		//注意：所有的域名要写在一个字符串里,中间用“,”号隔开
		
		
		//换算md5验证，必须要填写
		$hostrule_md5 = $this->_json2md5str($hostrule);
        $ipwhite_md5 = $this->_json2md5str($ipwhite);
        $macblack_md5 = $this->_json2md5str($macblack);
        $macwhite_md5 = $this->_json2md5str($macwhite);
        $domain_md5 = $this->_json2md5str($domain);
		
		//拼接返回结果
		$rule =$rule.json_encode(array('rule'=>array('host'=>$hostrule,'host_md5'=>$hostrule_md5,'ipwhite'=>$ipwhite,'ipwhite_md5'=>$ipwhite_md5,
            'macblack'=>$macblack,'macblack_md5'=>$macblack_md5,'macwhite'=>$macwhite,'macwhite_md5'=>$macwhite_md5,
            'domain'=>$domain,'domain_md5'=>$domain_md5)));
		
		echo 'Pong '.$rule;
	}
	
	/**
     * 认证用户登录页面
	 * 该页面用来用各种方式（用户名名、密码，随机码，微博，微信，qq，手机号码等）判定使用者的身份！
	 * 
	 * 认证后要做的事情：	1.认证不通过，还是继续回到该页面（大不要丢掉刚开始wifidog传递上来的参数）
	 *						2.通过认证：根据wifidog的参数，做页面重定向						
	 *
	 * 目前该页面采用了最简单的用户名、密码登录方式
     */
	public function login()
	{	
		session_start();
		$this->form_validation->set_rules('username', 'Title', 'required');
		$this->form_validation->set_rules('password', 'text', 'required');
        
		/*
		wifidog 带过来的参数主要有
		1.gw_id
		2.gw_address wifidog状态的访问地址
		3.gw_port 	wifidog状态的访问端口
		4.url 		被重定向的url（用户访问的url）
		~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		协议v2新增参数
		5.dev_id 设备id，45位字符串（用来区分不同的设备）
		接口v2.1.3新增加如下
		6.mac 客户端的mac地址
		*/	
		
		if ($this->form_validation->run() === FALSE)
		{
			
			if(!empty($_GET))
			{
				
				$data['gw_address'] = $_GET['gw_address'];
				$data['gw_port'] = $_GET['gw_port'];
				$data['gw_id'] = $_GET['gw_id'];
				$data['url'] = $_GET['url'];				
				$_SESSION['url'] = $_GET['url'];
				$_SESSION['gw_port'] = $_GET['gw_port'];
				$_SESSION['gw_address'] = $_GET['gw_address'];
				
			}else{
				$data['gw_address'] = '';
				$data['gw_port'] = '';
				$data['gw_id'] = '';
				$data['url'] = '';	
			}
			$data['form_url'] = base_url('wifidog/login');
			           
			//服务器验证页面
			if($this->is_mobile)
				$this->load->view('model1/wifidog_login_mobile',$data);
			else
				$this->load->view('model1/wifidog_login_pc',$data);
       	}
		else
		{
			//用户登录校验		
			
			//认证用户，此处直接跳过，不过校验
			if(true)
			//if( $this->input->post('username') ==='ApFree' and $this->input->post('password') === 'apfree')
			{
				//登录成功重定向到wifidog指定的gw
				//附带一个随机生成的token参数（md5），这个作为服务器认定客户的唯一标记
				redirect('http://'.$_SESSION['gw_address'].':'.$_SESSION['gw_port'].'/wifidog/auth?token='.md5(uniqid(rand(), 1)).'&url='.$_SESSION['url'], 'location', 302);
			}else{
				//不成功仍旧返回登录页面
				$data[$debug] = '登录失败';
                if($this->is_mobile)
                    //$this->load->view('model1/wifidog_login_mobile',$data);
					$this->load->view('model1/wifidog_login_mobile',$data);
                else
                    $this->load->view('model1/wifidog_login_pc',$data);
			}
		}
	}	
	/**
     * 认证接口
     */
	public function auth()
	{
		if(!$this->valid_agent)
			return;
		//响应客户端的定时认证，可在此处做各种统计、计费等等
		/*
		wifidog 会通过这个接口传递连接客户端的信息，然后根据返回，对客户端做开通、断开等处理，具体返回值可以看wifidog的文档
		wifidog主要提交如下参数
		1.ip
		2. mac
		3. token（login页面下发的token）
		4.incoming 下载流量
		5.outgoing 上传流量 
		6.stage  认证阶段，就两种 login 和 counters
		~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		协议v2 新增如下参数
		7.dev_id 设备id，45位字符串（用来区分不同的设备）
		8.uprate 该客户端该时刻即时上行速率，单位  bps
		9.downrate 该客户端该时刻即时下行速率，单位  bps
		10.gw_id
		11.client_name 客户端的设备名称，如果没有获取到，则用*表示
		*/
		
		
		$stage = $_GET['stage'] == 'counters'?'counters':'login';
		if($stage == 'login')
        {
			//XXXX跳过login 阶段的处理XXXX不能随便跳过的
			//默认返回 允许
			echo "Auth: 1";
		}
		else if($stage == 'counters')
		{
		
			//做一个简单的流量判断验证，下载流量超值时，返回下线通知，否则保持在线
			if(!empty($_GET['incoming']) and $_GET['incoming'] > 10000000)
			{
				echo "Auth: 0";
			}else{
				echo "Auth: 1\n";			
			}
		}
		else
			echo "Auth: 0"; //其他情况都返回拒绝
			
			
		/*
		返回值：主要有这两种就够了
		0 - 拒绝
		1 - 放行
		
		官方文档如下
		0 - AUTH_DENIED - User firewall users are deleted and the user removed.
		6 - AUTH_VALIDATION_FAILED - User email validation timeout has occured and user/firewall is deleted（用户邮件验证超时，防火墙关闭该用户）
		1 - AUTH_ALLOWED - User was valid, add firewall rules if not present
		5 - AUTH_VALIDATION - Permit user access to email to get validation email under default rules （用户邮件验证时，向用户开放email）
		-1 - AUTH_ERROR - An error occurred during the validation process
		*/
	}
	/**
     * portal 跳转接口
     */
	public function portal()
	{
		/*
			wifidog 带过来的参数 如下
			1. gw_id
			~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
			2.dev_id 设备id，45位字符串（用来区分不同的设备）
		*/
		
		//重定到指定网站 或者 显示splash广告页面		
		redirect('http://www.baidu.com', 'location', 302);
			
	}
    
    
    /**
     * wifidog 的gw_message 接口，信息提示页面
	 *
	 *------------------------------------------------------------------------------------
	 * 注：虽然此接口被用到的机会很少，但是这里有个问题需要说明下
	 * wifidog程序访问该接口的url为  /xxx/gw_message.php?message=XXX
	 * 这个是原版wifidog于其他4个接口风格很不一致的地方，导致了使用其他非php服务器端的问题
	 * 并且如果采php的CI框架，也有访问上的问题（其他框架就不知道了）
	 * 只能通过特殊的url重定向规则等外部配置才能实现该接口的访问
	 *
	 * apfree 的wifidog客户端会修复该问题，将其修改为 /xxx/gw_message/?message=XXX 的格式访问
	 *------------------------------------------------------------------------------------
     */
    function gw_message()
    {
        if (isset($_REQUEST["message"])) {
            switch ($_REQUEST["message"]) {
                case 'failed_validation': 
				//auth的stage为login时，被服务器返回AUTH_VALIDATION_FAILED时，来到该处处理
				//认证失败，请重新认证                    
                    break;                    
                case 'denied':
				//auth的stage为login时，被服务器返回AUTH_DENIED时，来到该处处理
				//认证被拒
                    break;                    
                case 'activate': 
				//auth的stage为login时，被服务器返回AUTH_VALIDATION时，来到该处处理
				//待激活
                    break;
                default:
                    break;
            }
        }else{
            //不回显任何信息
        }
    }
	
	
	 /**
      * 给规则数组换算md5 字符串
      * 
      * @param array  $arry
      * 
      * @return string md5字符串 
      */
     private function _json2md5str($arry=array())
     {
         //log_message('error','__json2md5str:'.json_encode($arry));
         return md5(json_encode($arry));       
     }
}

/* End of file wifidog.php */
/* Location: ./application/controllers/wifidog.php */