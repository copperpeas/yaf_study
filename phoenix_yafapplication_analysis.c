yaf处理一次web请求的详细过程(理解了这个过程就理解了yaf的实现原理):

application启动之后,yaf的对象关系如图:

|Application实例|       |Dispatcher实例|		   |Request实例|
_________________       _______________         ____________
|$dispatcher--- |---->  |$request-----|------->|$module    |
|$config\			    |$router\	  |        |$controller|
	/|\	 \						 \			   |$action    |
	 |	  \					 	  \
	 |	   \					   \
	 |	    \				   	    \
	 |	     \					     \
	 |	     \\|					 \\|Yf
	 |	     |Config实例|		|Router路由器实例|			|Router路由协议的容器|
	 |	     					|$routes--------|-------->  |				   |
	 |
new Yaf_Application()

::::::::::一切构造都从Yaf_Application开始:::::::::::
它做的事情(见yaf_application.c):
1. 实例化 Config
2. 实例化 Request
3. 实例化 Dispatcher
4. 实例化 Loader


几乎所有MVC框架处理请求的3个基本流程
request----->|   路由   |  根据request_uri确定处理请求的controller和action
				|
				|
				|
			   \|/
			 | 处理请求 | 调用action定义的函数来处理用户请求
			 	|
			 	|
			 	|
			   \|/
			 |返回数据| 把action返回的数据输出给客户端

yaf_application.c:run函数调用图谱:
								   run()
								    |
								   \|/
						yaf_dispatcher_dispatch()
					 	/     		|				\
					   /      		|				 \
				   	  /	   			|				  \
				  	 /		   		|				   \
				 	/		   		|					\
				   /		   		|					 \
			  	 |//		   	   \|/					 \\|
yaf_dispatcher_route()   	yaf_dispatcher_handle()     yaf_response_end()
		/					/				\
	   /				   /				 \
	  / 				  /					  \
	\//					 /					   \
yaf_router_route()	   |// 					   \\|
				   new controller			call  Action''s method

1.yaf_dispatcher_dispatch:	处理请求的中心函数.调用路由器完成路由,根据路由结果找到对应action,最后把结果发给客户端;还夹杂着一些对插件的调用.
2.yaf_dispatcher_route:		起到路由的作用,路由的目的是找出处理此次请求的module,controller,action,并把信息保存在Request对象中.
							调用yaf_router_route来执行路由.
3.yaf_dispatcher_handle:	调用action的方法来处理请求:处理请求之前,yaf_dispatcher_handle函数首先从Request对象读取路由结果(module,controller,action),
							然后创建controller对象,执行action定义的逻辑处理请求.需要注意的是yaf编写action有两种方式.
						1.把action方法写在controller类中.
							class Controller_Index extends Yaf_Controller_Abstract{
								public function indexAction(){echo "Hello, World!";}
							}
						2.在controller对象的$actions保存所有action序对,序对的键必须与路由结果的action名字一样,而且是小写,序对的值
						  是action类文件的相对路径,然后在action类的execute方法中写处理逻辑:
						  //controllers/Index.php
						  class Controller_Index extends Yaf_Controller_Abstract{
						  	public $action = array('index'=>'actions/Index.php',);
						  }
						  //actions/Index.php
						  class Action_Index extends Yaf_Action_Abstract{
						  	public function execute(){echo "hello,world";}
						  }
						  根据源码,yaf首先从controller中寻找方法,找到就执行,没找到,再从controller定义的$action数组中寻找Action类,
						  实例化这个Action类并执行其execute方法.

<img src="/home/shilun1/Desktop/yaf_flow.png"</img>
// /home/shilun1/Desktop/yaf_flow.png
官方对插件的说明:

触发顺序	名称						触发时机
1		routerStartup			在路由之前触发
2		routerShutdown			路由结束之后触发
3		dispatchLoopStartup		分发循环开始之前被触发
4		preDispatch				分发之前触发
5		postDispatch			分发结束之后触发
6		dispatchLoopShutdown	分发循环结束之后触发
扩展中插件方法名用宏表示
#define YAF_PLUGIN_HOOK_ROUTESTARTUP                "routerstartup"
#define YAF_PLUGIN_HOOK_ROUTESHUTDOWN               "routershutdown"
#define YAF_PLUGIN_HOOK_LOOPSTARTUP                 "dispatchloopstartup"
#define YAF_PLUGIN_HOOK_PREDISPATCH                 "predispatch"
#define YAF_PLUGIN_HOOK_POSTDISPATCH                "postdispatch"
#define YAF_PLUGIN_HOOK_LOOPSHUTDOWN                "dispatchloopshutdown"
#define YAF_PLUGIN_HOOK_PRERESPONSE                 "preresponse"

内置的路由协议
Yaf_Route_Static
Yaf_Route_Simple
Yaf_Route_Supervar
Yaf_Route_Rewrite
Yaf_Route_Regex
Yaf_Route_Map

上面几种路由协议源代码列表
yaf_route_static.c
yaf_route_simple.c
yaf_route_supervar.c
yaf_route_rewrite.c
yaf_route_regex.c
yaf_route_map.c
无路是哪个路由协议最后功能都是为了设置module，controller，action的名称

